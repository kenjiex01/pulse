@php
    $deduction = $deduction ?? null;
    $isEditDeduction = $deduction !== null;
    $fieldIdPrefix = $fieldIdPrefix ?? ($isEditDeduction ? 'edit-deduction-'.$deduction->employee_deduction_id : 'create-deduction');
    $deductionAmount = old('deduction_amount', $deduction?->deduction_amount);
    $deductionInterest = old('deduction_interest', $deduction?->deduction_interest ?? 0);
    $payrollTaken = $deduction?->payrollDeductionsTotal() ?? 0.0;
    $balance = round((float) ($deductionAmount ?: 0) + (float) ($deductionInterest ?: 0) - $payrollTaken, 2);
@endphp

<form
    method="POST"
    action="{{ $isEditDeduction
        ? route('employees.deductions.update', [$employee, $deduction])
        : route('employees.deductions.store', $employee) }}"
    class="space-y-4"
    data-employee-deduction-form
    data-deduction-payroll-payments="{{ number_format($payrollTaken, 2, '.', '') }}"
>
    @csrf
    @if ($isEditDeduction)
        @method('PUT')
        <input type="hidden" name="edit_deduction_id" value="{{ $deduction->employee_deduction_id }}">
    @else
        <input type="hidden" name="form_context" value="create-employee-deduction">
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="{{ $fieldIdPrefix }}-type" class="form-label">Deduction Type <span class="text-red-500">*</span></label>
            <select id="{{ $fieldIdPrefix }}-type" name="deduction_type_id" class="form-input" required>
                <option value="">Select deduction type</option>
                @foreach ($deductionTypes as $type)
                    <option
                        value="{{ $type->deduction_type_id }}"
                        @selected((string) old('deduction_type_id', $deduction?->deduction_type_id) === (string) $type->deduction_type_id)
                    >
                        {{ $type->description }}
                    </option>
                @endforeach
            </select>
            @error('deduction_type_id')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $fieldIdPrefix }}-date" class="form-label">Deduction Date <span class="text-red-500">*</span></label>
            <input
                id="{{ $fieldIdPrefix }}-date"
                type="date"
                name="dt_deduction"
                class="form-input"
                value="{{ old('dt_deduction', $deduction?->dt_deduction?->format('Y-m-d')) }}"
                required
            >
            @error('dt_deduction')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $fieldIdPrefix }}-amount" class="form-label">Deduction Amount <span class="text-red-500">*</span></label>
            <input
                id="{{ $fieldIdPrefix }}-amount"
                type="number"
                name="deduction_amount"
                class="form-input"
                step="0.01"
                min="0.01"
                data-deduction-amount
                value="{{ $deductionAmount }}"
                required
            >
            @error('deduction_amount')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $fieldIdPrefix }}-amortization" class="form-label">Deduction Amortization <span class="text-red-500">*</span></label>
            <input
                id="{{ $fieldIdPrefix }}-amortization"
                type="number"
                name="amortization_amount"
                class="form-input"
                step="0.01"
                min="0.01"
                value="{{ old('amortization_amount', $deduction?->amortization_amount) }}"
                required
            >
            <p class="mt-1 text-xs text-gray-500">Taken each pay period while this deduction type is checked on the payroll calendar, until the balance is zero.</p>
            @error('amortization_amount')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $fieldIdPrefix }}-interest" class="form-label">Deduction Interest</label>
            <input
                id="{{ $fieldIdPrefix }}-interest"
                type="number"
                name="deduction_interest"
                class="form-input"
                step="0.01"
                min="0"
                data-deduction-interest
                value="{{ $deductionInterest }}"
            >
            @error('deduction_interest')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $fieldIdPrefix }}-balance" class="form-label">Deduction Balance</label>
            <input
                id="{{ $fieldIdPrefix }}-balance"
                type="text"
                class="form-input bg-gray-50"
                data-deduction-balance
                value="{{ number_format($balance, 2, '.', '') }}"
                readonly
            >
            <p class="mt-1 text-xs text-gray-500">Amount + interest − amounts already taken in payroll.</p>
        </div>
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => $isEditDeduction ? 'Save Deduction' : 'Add Deduction',
    ])
</form>
