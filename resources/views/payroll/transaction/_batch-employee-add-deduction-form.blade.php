@php
    use App\Support\PayrollTransactionModule;
@endphp

<form
    method="POST"
    action="{{ route(PayrollTransactionModule::routeName('employees.deductions.store'), [$batch, $detail]) }}"
    class="space-y-4"
    data-batch-add-deduction-form
>
    @csrf
    <input type="hidden" name="form_context" value="add-batch-employee-deduction">
    <input type="hidden" name="payroll_batch_detail_id" value="{{ $detail->payroll_batch_detail_id }}">
    <input type="hidden" name="batch_detail_tab" value="deductions">
    <input type="hidden" name="batch_employee_search" value="{{ $batchEmployeeSearch ?? '' }}">
    <input type="hidden" name="search" value="{{ request('search', '') }}">

    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
        <p><span class="font-medium text-gray-900">Employee:</span> {{ $detail->employee?->employee_number ?? '—' }} · {{ $detail->employee?->full_name ?? '—' }}</p>
        <p class="mt-1"><span class="font-medium text-gray-900">Batch:</span> {{ $batch->formattedBatchNo() }}</p>
    </div>

    <div>
        <label for="batch-add-deduction-type" class="form-label">Deduction Type <span class="text-red-500">*</span></label>
        <select
            id="batch-add-deduction-type"
            name="deduction_type_id"
            class="form-input"
            required
            data-no-searchable-select
            data-batch-add-deduction-type
        >
            <option value="">Select deduction type</option>
            @foreach ($deductionTypes as $deductionType)
                <option
                    value="{{ $deductionType->deduction_type_id }}"
                    data-code="{{ $deductionType->deduction_type_code }}"
                    @selected((string) old('deduction_type_id') === (string) $deductionType->deduction_type_id)
                >
                    {{ $deductionType->deduction_type_code }} — {{ $deductionType->description }}
                </option>
            @endforeach
        </select>
        @error('deduction_type_id')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    @php
        $selectedDeductionCode = optional($deductionTypes->firstWhere('deduction_type_id', (int) old('deduction_type_id')))->deduction_type_code;
        $showHours = in_array($selectedDeductionCode, ['LTDE', 'UTDE'], true);
    @endphp

    <div data-batch-add-deduction-hours-wrap class="{{ $showHours ? '' : 'hidden' }}">
        <label for="batch-add-deduction-hours" class="form-label">Hours <span class="text-red-500">*</span></label>
        <input
            type="number"
            id="batch-add-deduction-hours"
            name="hours"
            min="0"
            step="0.01"
            class="form-input text-right"
            placeholder="0.00"
            value="{{ old('hours') }}"
            data-batch-add-deduction-hours
        >
        @error('hours')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="batch-add-deduction-employee-amount" class="form-label">Employee Amount</label>
            <input
                type="number"
                id="batch-add-deduction-employee-amount"
                name="employee_amount"
                min="0"
                step="0.01"
                class="form-input text-right"
                placeholder="0.00"
                value="{{ old('employee_amount') }}"
            >
            @error('employee_amount')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="batch-add-deduction-employer-amount" class="form-label">Employer Share</label>
            <input
                type="number"
                id="batch-add-deduction-employer-amount"
                name="employer_amount"
                min="0"
                step="0.01"
                class="form-input text-right"
                placeholder="0.00"
                value="{{ old('employer_amount') }}"
            >
            @error('employer_amount')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="batch-add-deduction-reference-number" class="form-label">Reference Number</label>
            <input
                type="text"
                id="batch-add-deduction-reference-number"
                name="reference_number"
                maxlength="45"
                class="form-input"
                value="{{ old('reference_number') }}"
            >
            @error('reference_number')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="batch-add-deduction-reference-date" class="form-label">Reference Date</label>
            <input
                type="date"
                id="batch-add-deduction-reference-date"
                name="reference_date"
                class="form-input"
                value="{{ old('reference_date') }}"
            >
            @error('reference_date')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <p class="text-xs text-gray-500">Government deductions (SSS, PhilHealth, Pag-IBIG, WHT) are computed automatically and cannot be added here.</p>

    @error('batch')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Add Deduction',
        'cancelModalId' => 'payroll-batch-add-deduction-modal',
    ])
</form>
