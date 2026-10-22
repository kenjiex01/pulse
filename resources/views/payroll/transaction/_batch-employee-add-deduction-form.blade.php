@php
    use App\Support\PayrollTransactionModule;

    $line = $line ?? null;
    $lineId = $line?->payroll_deduction_id;
    $idPrefix = $line ? 'batch-edit-deduction-'.$lineId : 'batch-add-deduction';
    $errorBag = $line ? 'editDeduction'.$lineId : 'default';
    $useOld = $line === null ? old('edit_payroll_deduction_id') === null : (int) old('edit_payroll_deduction_id') === (int) $lineId;
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
    action="{{ $line ? route('payroll.transaction.employees.deductions.update', [$batch, $detail, $line]) : route(PayrollTransactionModule::routeName('employees.deductions.store'), [$batch, $detail]) }}"
    class="space-y-4"
    data-batch-add-deduction-form
    data-attendance-preview-url="{{ route('payroll.transaction.employees.deductions.attendance-preview', [$batch, $detail]) }}"
>
    @csrf
    @if ($line)
        @method('PUT')
        <input type="hidden" name="edit_payroll_deduction_id" value="{{ $lineId }}">
    @else
        <input type="hidden" name="form_context" value="add-batch-employee-deduction">
    @endif
    <input type="hidden" name="payroll_batch_detail_id" value="{{ $detail->payroll_batch_detail_id }}">
    <input type="hidden" name="batch_detail_tab" value="deductions">
    <input type="hidden" name="batch_employee_search" value="{{ $batchEmployeeSearch ?? '' }}">
    <input type="hidden" name="search" value="{{ request('search', '') }}">

    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
        <p><span class="font-medium text-gray-900">Employee:</span> {{ $detail->employee?->employee_number ?? '—' }} · {{ $detail->employee?->full_name ?? '—' }}</p>
        <p class="mt-1"><span class="font-medium text-gray-900">Batch:</span> {{ $batch->formattedBatchNo() }}</p>
    </div>

    <div>
        <label for="{{ $idPrefix }}-type" class="form-label">Deduction Type <span class="text-red-500">*</span></label>
        <select
            id="{{ $idPrefix }}-type"
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
                    @selected((string) $fieldValue('deduction_type_id') === (string) $deductionType->deduction_type_id)
                >
                    {{ $deductionType->deduction_type_code }} — {{ $deductionType->description }}
                </option>
            @endforeach
        </select>
        @error('deduction_type_id', $errorBag)
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    @php
        $selectedDeductionCode = optional($deductionTypes->firstWhere('deduction_type_id', (int) $fieldValue('deduction_type_id')))->deduction_type_code;
        $showLateUndertimeFields = in_array($selectedDeductionCode, ['LTDE', 'UTDE'], true);
    @endphp

    <div data-batch-add-deduction-ltde-wrap class="{{ $showLateUndertimeFields ? '' : 'hidden' }} space-y-4">
        <input
            type="hidden"
            name="days"
            value="{{ old('days', $showLateUndertimeFields ? '1' : '') }}"
            data-batch-add-deduction-days
        >

        <div>
            <div data-batch-add-deduction-time-in-wrap class="{{ $selectedDeductionCode === 'LTDE' ? '' : 'hidden' }}">
                <label for="{{ $idPrefix }}-time-in" class="form-label">Time In <span class="text-red-500">*</span></label>
                <input
                    type="time"
                    id="{{ $idPrefix }}-time-in"
                    name="time_in"
                    class="form-input"
                    value="{{ $fieldValue('time_in') }}"
                    @required($selectedDeductionCode === 'LTDE')
                    data-batch-add-deduction-time-in
                >
                @error('time_in', $errorBag)
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div data-batch-add-deduction-time-out-wrap class="{{ $selectedDeductionCode === 'UTDE' ? '' : 'hidden' }}">
                <label for="{{ $idPrefix }}-time-out" class="form-label">Time Out <span class="text-red-500">*</span></label>
                <input
                    type="time"
                    id="{{ $idPrefix }}-time-out"
                    name="time_out"
                    class="form-input"
                    value="{{ $fieldValue('time_out') }}"
                    @required($selectedDeductionCode === 'UTDE')
                    data-batch-add-deduction-time-out
                >
                @error('time_out', $errorBag)
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div data-batch-add-deduction-hours-wrap>
            <label for="{{ $idPrefix }}-hours" class="form-label">Hours <span class="text-red-500">*</span></label>
            <input
                type="number"
                id="{{ $idPrefix }}-hours"
                name="hours"
                min="0"
                step="0.01"
                class="form-input text-right"
                placeholder="0.00"
                value="{{ $fieldValue('hours') }}"
                data-batch-add-deduction-hours
                @if ($showLateUndertimeFields) required @endif
            >
            @error('hours', $errorBag)
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div
            data-batch-add-deduction-attendance-hint
            class="rounded-md border border-[#00A3E6]/30 bg-[#00A3E6]/5 px-3 py-2 text-xs text-gray-700 hidden"
            role="status"
        ></div>

        <p class="text-xs text-gray-500">Pick the Reference Date (work date) and enter the actual time — Hours and Employee Amount compute automatically from the shift and hourly rate. The time is saved as this line's log for that day; raw biometric logs are not changed. Each entry counts as 1 day.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="{{ $idPrefix }}-employee-amount" class="form-label">Employee Amount</label>
            <input
                type="number"
                id="{{ $idPrefix }}-employee-amount"
                name="employee_amount"
                min="0"
                step="0.01"
                class="form-input text-right"
                placeholder="0.00"
                value="{{ $fieldValue('employee_amount') }}"
                data-batch-add-deduction-employee-amount
            >
            @error('employee_amount', $errorBag)
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}-employer-amount" class="form-label">Employer Share</label>
            <input
                type="number"
                id="{{ $idPrefix }}-employer-amount"
                name="employer_amount"
                min="0"
                step="0.01"
                class="form-input text-right"
                placeholder="0.00"
                value="{{ $fieldValue('employer_amount') }}"
                data-batch-add-deduction-employer-amount
            >
            @error('employer_amount', $errorBag)
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="{{ $idPrefix }}-reference-number" class="form-label">Reference Number</label>
            <input
                type="text"
                id="{{ $idPrefix }}-reference-number"
                name="reference_number"
                maxlength="45"
                class="form-input"
                value="{{ $fieldValue('reference_number') }}"
            >
            @error('reference_number', $errorBag)
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}-reference-date" class="form-label">
                Reference Date <span class="font-normal text-gray-500 {{ $showLateUndertimeFields ? '' : 'hidden' }}" data-batch-add-deduction-reference-date-required>(Work Date)</span>
                <span class="text-red-500 {{ $showLateUndertimeFields ? '' : 'hidden' }}" data-batch-add-deduction-reference-date-required>*</span>
            </label>
            <input
                type="date"
                id="{{ $idPrefix }}-reference-date"
                name="reference_date"
                class="form-input"
                value="{{ $fieldValue('reference_date') }}"
                data-batch-add-deduction-reference-date
                @if ($showLateUndertimeFields) required @endif
            >
            @error('reference_date', $errorBag)
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <p class="text-xs text-gray-500">Government deductions (SSS, PhilHealth, Pag-IBIG, WHT) are computed automatically and cannot be added here.</p>

    @error('batch', $errorBag)
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror

    @include('partials.modal-form-actions', [
        'submitLabel' => $line ? 'Update Deduction' : 'Add Deduction',
        'cancelModalId' => $line ? 'payroll-batch-edit-deduction-modal-'.$lineId : 'payroll-batch-add-deduction-modal',
    ])
</form>
