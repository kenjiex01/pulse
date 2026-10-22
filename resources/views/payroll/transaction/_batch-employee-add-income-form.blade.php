@php
    use App\Support\PayrollTransactionModule;

    $line = $line ?? null;
    $lineId = $line?->payroll_income_id;
    $idPrefix = $line ? 'batch-edit-income-'.$lineId : 'batch-add-income';
    $errorBag = $line ? 'editIncome'.$lineId : 'default';
    $useOld = $line === null ? old('edit_payroll_income_id') === null : (int) old('edit_payroll_income_id') === (int) $lineId;
    $fieldValue = function (string $field) use ($line, $useOld) {
        $current = match ($field) {
            'reference_date' => $line?->dt_reference?->format('Y-m-d'),
            default => $line?->{$field},
        };

        return $useOld ? old($field, $current) : $current;
    };
@endphp

<form
    method="POST"
    action="{{ $line ? route('payroll.transaction.employees.incomes.update', [$batch, $detail, $line]) : route(PayrollTransactionModule::routeName('employees.incomes.store'), [$batch, $detail]) }}"
    class="space-y-4"
    data-batch-add-income-form
>
    @csrf
    @if ($line)
        @method('PUT')
        <input type="hidden" name="edit_payroll_income_id" value="{{ $lineId }}">
    @else
        <input type="hidden" name="form_context" value="add-batch-employee-income">
    @endif
    <input type="hidden" name="payroll_batch_detail_id" value="{{ $detail->payroll_batch_detail_id }}">
    <input type="hidden" name="batch_detail_tab" value="incomes">
    <input type="hidden" name="batch_employee_search" value="{{ $batchEmployeeSearch ?? '' }}">
    <input type="hidden" name="search" value="{{ request('search', '') }}">

    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
        <p><span class="font-medium text-gray-900">Employee:</span> {{ $detail->employee?->employee_number ?? '—' }} · {{ $detail->employee?->full_name ?? '—' }}</p>
        <p class="mt-1"><span class="font-medium text-gray-900">Batch:</span> {{ $batch->formattedBatchNo() }}</p>
    </div>

    <div>
        <label for="{{ $idPrefix }}-type" class="form-label">Income Type <span class="text-red-500">*</span></label>
        <select
            id="{{ $idPrefix }}-type"
            name="income_type_id"
            class="form-input"
            required
            data-no-searchable-select
            data-batch-add-income-type
        >
            <option value="">Select income type</option>
            @foreach ($incomeTypes as $incomeType)
                <option
                    value="{{ $incomeType->income_type_id }}"
                    data-code="{{ $incomeType->income_type_code }}"
                    @selected((string) $fieldValue('income_type_id') === (string) $incomeType->income_type_id)
                >
                    {{ $incomeType->income_type_code }} — {{ $incomeType->description }}
                </option>
            @endforeach
        </select>
        @error('income_type_id', $errorBag)
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    @php
        $selectedIncomeCode = optional($incomeTypes->firstWhere('income_type_id', (int) $fieldValue('income_type_id')))->income_type_code;
        $showHoursDays = in_array($selectedIncomeCode, ['BASC', 'OVRT'], true);
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 {{ $showHoursDays ? '' : 'hidden' }}" data-batch-add-income-hours-days-wrap>
        <div>
            <label for="{{ $idPrefix }}-hours" class="form-label">Hours <span class="text-red-500">*</span></label>
            <input
                type="number"
                id="{{ $idPrefix }}-hours"
                name="hours"
                min="0"
                step="0.0001"
                class="form-input text-right"
                placeholder="0.00"
                value="{{ $fieldValue('hours') }}"
                data-batch-add-income-hours
                @if ($showHoursDays) required @endif
            >
            @error('hours', $errorBag)
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}-days" class="form-label">Days <span class="text-red-500">*</span></label>
            <input
                type="number"
                id="{{ $idPrefix }}-days"
                name="days"
                min="0"
                step="0.0001"
                class="form-input text-right"
                placeholder="0.00"
                value="{{ $fieldValue('days') }}"
                data-batch-add-income-days
                @if ($showHoursDays) required @endif
            >
            @error('days', $errorBag)
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="{{ $idPrefix }}-taxable" class="form-label">Taxable</label>
            <input
                type="number"
                id="{{ $idPrefix }}-taxable"
                name="taxable"
                min="0"
                step="0.01"
                class="form-input text-right"
                placeholder="0.00"
                value="{{ $fieldValue('taxable') }}"
            >
            @error('taxable', $errorBag)
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}-non-taxable" class="form-label">Non-Taxable</label>
            <input
                type="number"
                id="{{ $idPrefix }}-non-taxable"
                name="non_taxable"
                min="0"
                step="0.01"
                class="form-input text-right"
                placeholder="0.00"
                value="{{ $fieldValue('non_taxable') }}"
            >
            @error('non_taxable', $errorBag)
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <p class="text-xs text-gray-500">Enter at least one amount greater than zero. Government deductions (SSS, PhilHealth, Pag-IBIG, WHT) will be recalculated when taxable income changes.</p>

    @error('batch', $errorBag)
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror

    @include('partials.modal-form-actions', [
        'submitLabel' => $line ? 'Update Income' : 'Add Income',
        'cancelModalId' => $line ? 'payroll-batch-edit-income-modal-'.$lineId : 'payroll-batch-add-income-modal',
    ])
</form>
