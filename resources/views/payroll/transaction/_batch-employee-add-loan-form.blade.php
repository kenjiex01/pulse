@php
    use App\Support\PayrollTransactionModule;

    $line = $line ?? null;
    $lineId = $line?->payroll_loan_payment_id;
    $idPrefix = $line ? 'batch-edit-loan-'.$lineId : 'batch-add-loan';
    $errorBag = $line ? 'editLoanPayment'.$lineId : 'default';
    $useOld = $line === null ? old('edit_payroll_loan_payment_id') === null : (int) old('edit_payroll_loan_payment_id') === (int) $lineId;
    $fieldValue = function (string $field) use ($line, $useOld) {
        $current = $line?->{$field};

        return $useOld ? old($field, $current) : $current;
    };
    $availableLoans = $availableLoans ?? collect();
    $lineLoan = $line?->employeeLoan;
@endphp

<form
    method="POST"
    action="{{ $line ? route('payroll.transaction.employees.loans.update', [$batch, $detail, $line]) : route(PayrollTransactionModule::routeName('employees.loans.store'), [$batch, $detail]) }}"
    class="space-y-4"
    data-batch-add-loan-form
>
    @csrf
    @if ($line)
        @method('PUT')
        <input type="hidden" name="edit_payroll_loan_payment_id" value="{{ $lineId }}">
    @else
        <input type="hidden" name="form_context" value="add-batch-employee-loan">
    @endif
    <input type="hidden" name="payroll_batch_detail_id" value="{{ $detail->payroll_batch_detail_id }}">
    <input type="hidden" name="batch_detail_tab" value="loans">
    <input type="hidden" name="batch_employee_search" value="{{ $batchEmployeeSearch ?? '' }}">
    <input type="hidden" name="search" value="{{ request('search', '') }}">

    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
        <p><span class="font-medium text-gray-900">Employee:</span> {{ $detail->employee?->employee_number ?? '—' }} · {{ $detail->employee?->full_name ?? '—' }}</p>
        <p class="mt-1"><span class="font-medium text-gray-900">Batch:</span> {{ $batch->formattedBatchNo() }}</p>
    </div>

    @if ($line)
        <div class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700">
            <p><span class="font-medium text-gray-900">Loan:</span> {{ $lineLoan?->loanType?->loan_type_code ?? '—' }} — {{ $lineLoan?->loanType?->description ?? '' }}</p>
            <p class="mt-1"><span class="font-medium text-gray-900">Loan Date:</span> {{ $lineLoan?->dt_loan?->format('M d, Y') ?? '—' }}</p>
            <p class="mt-1"><span class="font-medium text-gray-900">Available Balance:</span> {{ number_format(($lineLoan?->loanBalance() ?? 0) + $line->amount(), 2) }}</p>
        </div>
    @else
        <div>
            <label for="{{ $idPrefix }}-loan" class="form-label">Loan <span class="text-red-500">*</span></label>
            <select
                id="{{ $idPrefix }}-loan"
                name="employee_loan_id"
                class="form-input"
                required
                data-no-searchable-select
                data-batch-add-loan-select
            >
                <option value="">Select loan</option>
                @foreach ($availableLoans as $loan)
                    @php
                        $onDetail = $loan->payments
                            ->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)
                            ->sum(fn ($payment) => $payment->amount());
                        $available = round($loan->loanBalance() + $onDetail, 2);
                        $suggested = min($available, (float) ($loan->amortization_amount ?? $available));
                    @endphp
                    <option
                        value="{{ $loan->employee_loan_id }}"
                        data-suggested-payment="{{ number_format($suggested, 2, '.', '') }}"
                        @selected((string) $fieldValue('employee_loan_id') === (string) $loan->employee_loan_id)
                    >
                        {{ $loan->loanType?->loan_type_code ?? 'Loan' }} — {{ $loan->dt_loan?->format('M d, Y') ?? 'No date' }} · Balance {{ number_format($available, 2) }}
                    </option>
                @endforeach
            </select>
            @if ($availableLoans->isEmpty())
                <p class="mt-1 text-xs text-gray-500">This employee has no loan with a remaining balance. Add the loan in the employee profile first.</p>
            @endif
            @error('employee_loan_id', $errorBag)
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="{{ $idPrefix }}-payment" class="form-label">Payment <span class="text-red-500">*</span></label>
            <input
                type="number"
                id="{{ $idPrefix }}-payment"
                name="payment"
                min="0.01"
                step="0.01"
                class="form-input text-right"
                placeholder="0.00"
                required
                value="{{ $fieldValue('payment') }}"
                data-batch-add-loan-payment
            >
            @error('payment', $errorBag)
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}-penalty" class="form-label">Penalty</label>
            <input
                type="number"
                id="{{ $idPrefix }}-penalty"
                name="penalty"
                min="0"
                step="0.01"
                class="form-input text-right"
                placeholder="0.00"
                value="{{ $fieldValue('penalty') }}"
            >
            @error('penalty', $errorBag)
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <p class="text-xs text-gray-500">This deduction is saved to the employee's loan payment history and reduces the loan balance. It replaces the automatic amortization for the same loan in this batch and is kept when the batch is reprocessed.</p>

    @error('batch', $errorBag)
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror

    @include('partials.modal-form-actions', [
        'submitLabel' => $line ? 'Update Loan Deduction' : 'Add Loan Deduction',
        'cancelModalId' => $line ? 'payroll-batch-edit-loan-modal-'.$lineId : 'payroll-batch-add-loan-modal',
    ])
</form>
