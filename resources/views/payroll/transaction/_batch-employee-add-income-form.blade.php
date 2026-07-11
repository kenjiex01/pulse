@php
    use App\Support\PayrollTransactionModule;
@endphp

<form
    method="POST"
    action="{{ route(PayrollTransactionModule::routeName('employees.incomes.store'), [$batch, $detail]) }}"
    class="space-y-4"
>
    @csrf
    <input type="hidden" name="form_context" value="add-batch-employee-income">
    <input type="hidden" name="payroll_batch_detail_id" value="{{ $detail->payroll_batch_detail_id }}">
    <input type="hidden" name="batch_detail_tab" value="incomes">
    <input type="hidden" name="batch_employee_search" value="{{ $batchEmployeeSearch ?? '' }}">
    <input type="hidden" name="search" value="{{ request('search', '') }}">

    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
        <p><span class="font-medium text-gray-900">Employee:</span> {{ $detail->employee?->employee_number ?? '—' }} · {{ $detail->employee?->full_name ?? '—' }}</p>
        <p class="mt-1"><span class="font-medium text-gray-900">Batch:</span> {{ $batch->formattedBatchNo() }}</p>
    </div>

    <div>
        <label for="batch-add-income-type" class="form-label">Income Type <span class="text-red-500">*</span></label>
        <select
            id="batch-add-income-type"
            name="income_type_id"
            class="form-input"
            required
            data-no-searchable-select
        >
            <option value="">Select income type</option>
            @foreach ($incomeTypes as $incomeType)
                <option value="{{ $incomeType->income_type_id }}" @selected((string) old('income_type_id') === (string) $incomeType->income_type_id)>
                    {{ $incomeType->income_type_code }} — {{ $incomeType->description }}
                </option>
            @endforeach
        </select>
        @error('income_type_id')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="batch-add-income-taxable" class="form-label">Taxable</label>
            <input
                type="number"
                id="batch-add-income-taxable"
                name="taxable"
                min="0"
                step="0.01"
                class="form-input text-right"
                placeholder="0.00"
                value="{{ old('taxable') }}"
            >
            @error('taxable')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="batch-add-income-non-taxable" class="form-label">Non-Taxable</label>
            <input
                type="number"
                id="batch-add-income-non-taxable"
                name="non_taxable"
                min="0"
                step="0.01"
                class="form-input text-right"
                placeholder="0.00"
                value="{{ old('non_taxable') }}"
            >
            @error('non_taxable')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <p class="text-xs text-gray-500">Enter at least one amount greater than zero. Government deductions (SSS, PhilHealth, Pag-IBIG, WHT) will be recalculated when taxable income changes.</p>

    @error('batch')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Add Income',
        'cancelModalId' => 'payroll-batch-add-income-modal',
    ])
</form>
