@php
    $loan = $loan ?? null;
    $isEditLoan = $loan !== null;
    $fieldIdPrefix = $fieldIdPrefix ?? ($isEditLoan ? 'edit-loan-'.$loan->employee_loan_id : 'create-loan');
    $cancelModalId = $cancelModalId ?? ($isEditLoan
        ? 'employee-loan-edit-modal-'.$loan->employee_loan_id
        : 'employee-loan-create-modal');

    $paymentSchemeId = (int) old(
        'payment_scheme_id',
        $loan?->payment_scheme_id ?? \App\Models\PaymentScheme::USER_ENTERED_AMORTIZATION
    );
    $loanAmount = old('loan_amount', $loan?->loan_amount);
    $loanInterest = old('loan_interest', $loan?->loan_interest);
    $paidFromPrevious = old('paid_from_previous', $loan?->paid_from_previous ?? 0);
    $deductedFromNewLoan = old('deducted_from_new_loan', $loan?->deducted_from_new_loan ?? 0);
    $amortizationAmount = old('amortization_amount', $loan?->amortization_amount);
    $numberOfPayments = old('number_of_payments', $loan?->number_of_payments);
    $paymentCount = $numberOfPayments !== null && $numberOfPayments !== '' ? (int) $numberOfPayments : null;
    $amortizationValue = $amortizationAmount !== null && $amortizationAmount !== '' ? (float) $amortizationAmount : null;
    $principal = \App\Models\EmployeeLoan::computePrincipalAmount(
        (float) ($loanAmount ?? 0),
        $loanInterest !== null && $loanInterest !== '' ? (float) $loanInterest : null,
        $amortizationValue,
        $paymentCount,
    );
    $interestFromPayments = $paymentCount !== null && $paymentCount > 0 && $amortizationValue !== null;
    if ($interestFromPayments) {
        $loanInterest = \App\Models\EmployeeLoan::computeLoanInterest($principal, (float) ($loanAmount ?? 0));
    }
    $balance = round(
        $principal
        - (float) ($paidFromPrevious ?: 0)
        - (float) ($deductedFromNewLoan ?: 0),
        2
    );
@endphp

<form
    method="POST"
    action="{{ $isEditLoan
        ? route('employees.loans.update', [$employee, $loan])
        : route('employees.loans.store', $employee) }}"
    class="space-y-4"
    data-employee-loan-form
>
    @csrf
    @if ($isEditLoan)
        @method('PUT')
        <input type="hidden" name="edit_loan_id" value="{{ $loan->employee_loan_id }}">
    @else
        <input type="hidden" name="form_context" value="create-employee-loan">
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="{{ $fieldIdPrefix }}-loan-type" class="form-label">Loan Type <span class="text-red-500">*</span></label>
            <select id="{{ $fieldIdPrefix }}-loan-type" name="loan_type_id" class="form-input" required>
                <option value="">Select loan type</option>
                @foreach ($loanTypes as $loanType)
                    <option
                        value="{{ $loanType->loan_type_id }}"
                        @selected((string) old('loan_type_id', $loan?->loan_type_id) === (string) $loanType->loan_type_id)
                    >
                        {{ $loanType->description }}
                    </option>
                @endforeach
            </select>
            @error('loan_type_id')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $fieldIdPrefix }}-dt-loan" class="form-label">Loan Date <span class="text-red-500">*</span></label>
            <input
                id="{{ $fieldIdPrefix }}-dt-loan"
                type="date"
                name="dt_loan"
                class="form-input"
                value="{{ old('dt_loan', $loan?->dt_loan?->format('Y-m-d')) }}"
                required
            >
            @error('dt_loan')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $fieldIdPrefix }}-payment-scheme" class="form-label">Payment Scheme <span class="text-red-500">*</span></label>
            <select
                id="{{ $fieldIdPrefix }}-payment-scheme"
                name="payment_scheme_id"
                class="form-input"
                required
                data-loan-payment-scheme
            >
                @foreach ($paymentSchemes as $scheme)
                    <option
                        value="{{ $scheme->payment_scheme_id }}"
                        @selected($paymentSchemeId === (int) $scheme->payment_scheme_id)
                    >
                        {{ $scheme->payment_scheme }}
                    </option>
                @endforeach
            </select>
            @error('payment_scheme_id')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $fieldIdPrefix }}-nop" class="form-label">No. of Payments</label>
            <input
                id="{{ $fieldIdPrefix }}-nop"
                type="number"
                name="number_of_payments"
                class="form-input"
                min="1"
                max="999"
                step="1"
                value="{{ old('number_of_payments', $loan?->number_of_payments) }}"
                data-loan-number-of-payments
            >
            @error('number_of_payments')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $fieldIdPrefix }}-loan-amount" class="form-label">Loan Amount <span class="text-red-500">*</span></label>
            <input
                id="{{ $fieldIdPrefix }}-loan-amount"
                type="number"
                name="loan_amount"
                class="form-input text-right"
                min="0"
                step="0.01"
                value="{{ $loanAmount }}"
                required
                data-loan-amount
            >
            @error('loan_amount')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $fieldIdPrefix }}-principal" class="form-label">Principal Loan Amount</label>
            <input
                id="{{ $fieldIdPrefix }}-principal"
                type="text"
                class="form-input text-right bg-gray-50"
                value="{{ number_format($principal, 2, '.', '') }}"
                readonly
                tabindex="-1"
                data-loan-principal
            >
            <p class="mt-1 text-xs text-gray-500" data-loan-principal-hint>{{ ($numberOfPayments !== null && $numberOfPayments !== '' && (int) $numberOfPayments > 0) ? 'Amortization × no. of payments' : 'Loan amount + interest' }}</p>
        </div>

        <div>
            <label for="{{ $fieldIdPrefix }}-amortization" class="form-label">Amortization Amount <span class="text-red-500">*</span></label>
            <input
                id="{{ $fieldIdPrefix }}-amortization"
                type="number"
                name="amortization_amount"
                class="form-input text-right"
                min="0"
                step="0.01"
                value="{{ old('amortization_amount', $loan?->amortization_amount) }}"
                data-loan-amortization
                required
            >
            @error('amortization_amount')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $fieldIdPrefix }}-start-payment" class="form-label">Payment Start Date</label>
            <input
                id="{{ $fieldIdPrefix }}-start-payment"
                type="date"
                name="dt_start_payment"
                class="form-input"
                value="{{ old('dt_start_payment', $loan?->dt_start_payment?->format('Y-m-d')) }}"
            >
            @error('dt_start_payment')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $fieldIdPrefix }}-interest" class="form-label">Loan Interest</label>
            <input
                id="{{ $fieldIdPrefix }}-interest"
                type="number"
                name="loan_interest"
                class="form-input text-right"
                min="0"
                step="0.01"
                value="{{ $loanInterest }}"
                data-loan-interest
                @readonly($interestFromPayments)
            >
            @if ($interestFromPayments)
                <p class="mt-1 text-xs text-gray-500">Principal loan amount − loan amount</p>
            @endif
            @error('loan_interest')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $fieldIdPrefix }}-paid-previous" class="form-label">Paid From Previous</label>
            <input
                id="{{ $fieldIdPrefix }}-paid-previous"
                type="number"
                name="paid_from_previous"
                class="form-input text-right"
                min="0"
                step="0.01"
                value="{{ $paidFromPrevious }}"
                data-loan-paid-previous
            >
            @error('paid_from_previous')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $fieldIdPrefix }}-deducted-new" class="form-label">Deducted From New Loan</label>
            <input
                id="{{ $fieldIdPrefix }}-deducted-new"
                type="number"
                name="deducted_from_new_loan"
                class="form-input text-right"
                min="0"
                step="0.01"
                value="{{ $deductedFromNewLoan }}"
                data-loan-deducted-new
            >
            @error('deducted_from_new_loan')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $fieldIdPrefix }}-balance" class="form-label">Loan Balance</label>
            <input
                id="{{ $fieldIdPrefix }}-balance"
                type="text"
                class="form-input text-right bg-gray-50 font-semibold"
                value="{{ number_format($balance, 2, '.', '') }}"
                readonly
                tabindex="-1"
                data-loan-balance
            >
        </div>

        <div class="sm:col-span-2 space-y-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
            <label class="flex items-start gap-2 text-sm text-gray-800">
                <input
                    type="checkbox"
                    name="deduct_half_per_cutoff"
                    value="1"
                    class="mt-0.5"
                    data-loan-half-cutoff
                    @checked(old('deduct_half_per_cutoff', $loan?->deduct_half_per_cutoff) && blank(old('deduction_cutoff', $loan?->deduction_cutoff)))
                    @disabled(filled(old('deduction_cutoff', $loan?->deduction_cutoff)))
                >
                <span>
                    <span class="font-medium">Deduct half per cut off</span>
                    <span class="mt-0.5 block text-xs text-gray-500">Each matching cut off deducts half of the amortization amount.</span>
                </span>
            </label>

            <fieldset>
                <legend class="form-label">Deduct on</legend>
                <div class="mt-2 flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 text-sm text-gray-800">
                        <input
                            type="radio"
                            name="deduction_cutoff"
                            value="{{ \App\Models\EmployeeLoan::CUTOFF_FIRST }}"
                            data-loan-cutoff
                            @checked((string) old('deduction_cutoff', $loan?->deduction_cutoff) === (string) \App\Models\EmployeeLoan::CUTOFF_FIRST)
                        >
                        1st cut off
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-800">
                        <input
                            type="radio"
                            name="deduction_cutoff"
                            value="{{ \App\Models\EmployeeLoan::CUTOFF_SECOND }}"
                            data-loan-cutoff
                            @checked((string) old('deduction_cutoff', $loan?->deduction_cutoff) === (string) \App\Models\EmployeeLoan::CUTOFF_SECOND)
                        >
                        2nd cut off
                    </label>
                </div>
                <p class="mt-2 text-xs text-gray-500">1st cut off ends on or before the 15th. 2nd cut off ends after the 15th. Choosing a cut off turns off Deduct half per cut off. Click the selected cut off again to clear it. Nothing is deducted unless this loan type is checked on that payroll calendar.</p>
            </fieldset>
            @error('deduction_cutoff')
                <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="sm:col-span-2">
            <label for="{{ $fieldIdPrefix }}-purpose" class="form-label">Loan Purpose</label>
            <input
                id="{{ $fieldIdPrefix }}-purpose"
                type="text"
                name="loan_purpose"
                class="form-input"
                maxlength="150"
                value="{{ old('loan_purpose', $loan?->loan_purpose) }}"
                placeholder="Optional"
            >
            @error('loan_purpose')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => $isEditLoan ? 'Save Changes' : 'Add Loan',
        'cancelModalId' => $cancelModalId,
    ])
</form>
