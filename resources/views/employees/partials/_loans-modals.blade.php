@php
    use App\Models\LoanType;
    use App\Models\PaymentScheme;

    $loans = $employee->relationLoaded('loans')
        ? $employee->loans
        : $employee->loans()->with([
            'loanType',
            'paymentScheme',
            'payments.payrollBatchDetail.payrollBatch.status',
            'payments.payrollBatchDetail.payrollBatch.payrollCalendar',
        ])->get();

    $loanTypes = LoanType::query()
        ->where('is_active', true)
        ->orderBy('description')
        ->get();

    $paymentSchemes = PaymentScheme::query()
        ->orderBy('payment_scheme_id')
        ->get();

    $openCreateLoanModal = old('form_context') === 'create-employee-loan'
        || request()->boolean('create');

    $editLoanId = old('edit_loan_id', request('edit_loan'));
@endphp

@foreach ($loans as $loan)
    @php
        $loanPayments = $loan->relationLoaded('payments')
            ? $loan->payments
            : $loan->payments()->with([
                'payrollBatchDetail.payrollBatch.status',
                'payrollBatchDetail.payrollBatch.payrollCalendar',
            ])->get();

        $loanPayments = $loanPayments
            ->sortBy(function ($payment) {
                $calendar = $payment->payrollBatchDetail?->payrollBatch?->payrollCalendar;

                return $calendar?->dt_from?->format('Y-m-d') ?? '9999-12-31';
            })
            ->values();
    @endphp

    @include('partials.modal', [
        'id' => 'employee-loan-view-modal-'.$loan->employee_loan_id,
        'title' => 'Loan Payroll Deductions',
        'description' => ($loan->loanType?->description ?? 'Loan').' · balance '.number_format($loan->loanBalance(), 2),
        'panelClass' => 'max-w-4xl',
        'body' => view('employees.partials._loan-payments', [
            'loan' => $loan,
            'payments' => $loanPayments,
        ])->render(),
    ])

    <form
        id="destroy-employee-loan-{{ $loan->employee_loan_id }}"
        method="POST"
        action="{{ route('employees.loans.destroy', [$employee, $loan]) }}"
        class="hidden"
    >
        @csrf
        @method('DELETE')
    </form>

    @include('partials.modal', [
        'id' => 'employee-loan-edit-modal-'.$loan->employee_loan_id,
        'title' => 'Edit Loan',
        'description' => 'Update this employee loan record.',
        'panelClass' => 'max-w-3xl',
        'open' => (string) $editLoanId === (string) $loan->employee_loan_id,
        'body' => view('employees.partials._loan-form', [
            'employee' => $employee,
            'loan' => $loan,
            'loanTypes' => $loanTypes,
            'paymentSchemes' => $paymentSchemes,
            'fieldIdPrefix' => 'edit-loan-'.$loan->employee_loan_id,
            'cancelModalId' => 'employee-loan-edit-modal-'.$loan->employee_loan_id,
        ])->render(),
    ])
@endforeach

@include('partials.modal', [
    'id' => 'employee-loan-create-modal',
    'title' => 'Add Loan',
    'description' => 'Create a new loan record for this employee.',
    'panelClass' => 'max-w-3xl',
    'open' => $openCreateLoanModal,
    'body' => view('employees.partials._loan-form', [
        'employee' => $employee,
        'loan' => null,
        'loanTypes' => $loanTypes,
        'paymentSchemes' => $paymentSchemes,
        'fieldIdPrefix' => 'create-loan',
        'cancelModalId' => 'employee-loan-create-modal',
    ])->render(),
])
