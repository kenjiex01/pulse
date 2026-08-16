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
    $principal = \App\Models\EmployeeLoan::computePrincipal(
        (float) ($loanAmount ?? 0),
        $loanInterest !== null && $loanInterest !== '' ? (float) $loanInterest : null
    );
    $balance = round(
        (float) ($loanAmount ?? 0)
        + (float) ($loanInterest ?: 0)
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
                @disabled($paymentSchemeId !== \App\Models\PaymentScheme::BASED_ON_NUMBER_OF_PAYMENTS)
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
            <p class="mt-1 text-xs text-gray-500">Loan amount + interest</p>
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
                @disabled($paymentSchemeId === \App\Models\PaymentScheme::BASED_ON_NUMBER_OF_PAYMENTS)
                @required($paymentSchemeId === \App\Models\PaymentScheme::USER_ENTERED_AMORTIZATION)
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
            >
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
