@php
    $deductions = $employee->relationLoaded('employeeDeductions')
        ? $employee->employeeDeductions
        : $employee->employeeDeductions()->with([
            'deductionType',
            'payrollDeductions.payrollBatchDetail.payrollBatch.status',
            'payrollDeductions.payrollBatchDetail.payrollBatch.payrollCalendar',
        ])->get();

    $deductionTypes = \App\Models\EmployeeDeduction::selectableTypesQuery()->get();

    $openCreateDeductionModal = old('form_context') === 'create-employee-deduction';
    $editDeductionId = old('edit_deduction_id', request('edit_deduction'));
@endphp

@foreach ($deductions as $deduction)
    @php
        $lines = $deduction->relationLoaded('payrollDeductions')
            ? $deduction->payrollDeductions
            : $deduction->payrollDeductions()->with([
                'payrollBatchDetail.payrollBatch.status',
                'payrollBatchDetail.payrollBatch.payrollCalendar',
            ])->get();

        $lines = $lines
            ->sortBy(function ($line) {
                $calendar = $line->payrollBatchDetail?->payrollBatch?->payrollCalendar;

                return $calendar?->dt_from?->format('Y-m-d') ?? '9999-12-31';
            })
            ->values();
    @endphp

    @include('partials.modal', [
        'id' => 'employee-deduction-view-modal-'.$deduction->employee_deduction_id,
        'title' => 'Deduction Payroll Lines',
        'description' => ($deduction->deductionType?->description ?? 'Deduction').' · balance '.number_format($deduction->balance(), 2),
        'panelClass' => 'max-w-4xl',
        'body' => view('employees.partials._deduction-payments', [
            'deduction' => $deduction,
            'lines' => $lines,
        ])->render(),
    ])

    <form
        id="destroy-employee-deduction-{{ $deduction->employee_deduction_id }}"
        method="POST"
        action="{{ route('employees.deductions.destroy', [$employee, $deduction]) }}"
        class="hidden"
    >
        @csrf
        @method('DELETE')
    </form>

    @include('partials.modal', [
        'id' => 'employee-deduction-edit-modal-'.$deduction->employee_deduction_id,
        'title' => 'Edit Deduction',
        'description' => 'Update this employee deduction record.',
        'panelClass' => 'max-w-3xl',
        'open' => (string) $editDeductionId === (string) $deduction->employee_deduction_id,
        'body' => view('employees.partials._deduction-form', [
            'employee' => $employee,
            'deduction' => $deduction,
            'deductionTypes' => $deductionTypes,
            'fieldIdPrefix' => 'edit-deduction-'.$deduction->employee_deduction_id,
        ])->render(),
    ])
@endforeach

@include('partials.modal', [
    'id' => 'employee-deduction-create-modal',
    'title' => 'Add Deduction',
    'description' => 'Create a standing deduction for this employee. Payroll takes the amortization only when this deduction type is checked on the pay period.',
    'panelClass' => 'max-w-3xl',
    'open' => $openCreateDeductionModal,
    'body' => view('employees.partials._deduction-form', [
        'employee' => $employee,
        'deduction' => null,
        'deductionTypes' => $deductionTypes,
        'fieldIdPrefix' => 'create-deduction',
    ])->render(),
])
