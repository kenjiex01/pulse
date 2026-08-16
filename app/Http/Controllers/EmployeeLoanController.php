<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\PaymentScheme;
use App\Services\SysLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeLoanController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $validated = $this->validated($request, $employee);

        $loan = EmployeeLoan::query()->create([
            'employee_id' => $employee->employee_id,
            ...$validated,
        ]);

        SysLogService::record(
            action: 'create',
            table: 'tbl_employee_loans',
            recordId: $loan->employee_loan_id,
            newValues: $loan->only([
                'employee_id',
                'loan_type_id',
                'payment_scheme_id',
                'dt_loan',
                'number_of_payments',
                'principal_loan_amount',
                'loan_amount',
                'amortization_amount',
                'loan_interest',
                'paid_from_previous',
                'deducted_from_new_loan',
                'loan_purpose',
                'dt_start_payment',
            ]),
            description: 'Added employee loan for '.$employee->employee_number,
        );

        return redirect()
            ->route('employees.edit', ['employee' => $employee, 'tab' => 'loans'])
            ->with('success', 'Loan added.');
    }

    public function update(Request $request, Employee $employee, EmployeeLoan $loan): RedirectResponse
    {
        $this->authorize('update', $employee);
        $this->ensureBelongsToEmployee($employee, $loan);

        $validated = $this->validated($request, $employee, $loan);

        $old = $loan->only([
            'loan_type_id',
            'payment_scheme_id',
            'dt_loan',
            'number_of_payments',
            'principal_loan_amount',
            'loan_amount',
            'amortization_amount',
            'loan_interest',
            'paid_from_previous',
            'deducted_from_new_loan',
            'loan_purpose',
            'dt_start_payment',
        ]);

        $loan->update($validated);

        SysLogService::record(
            action: 'update',
            table: 'tbl_employee_loans',
            recordId: $loan->employee_loan_id,
            oldValues: $old,
            newValues: $loan->fresh()?->only(array_keys($old)),
            description: 'Updated employee loan for '.$employee->employee_number,
        );

        return redirect()
            ->route('employees.edit', ['employee' => $employee, 'tab' => 'loans'])
            ->with('success', 'Loan updated.');
    }

    public function destroy(Employee $employee, EmployeeLoan $loan): RedirectResponse
    {
        $this->authorize('update', $employee);
        $this->ensureBelongsToEmployee($employee, $loan);

        $old = $loan->only([
            'loan_type_id',
            'payment_scheme_id',
            'dt_loan',
            'loan_amount',
            'amortization_amount',
            'loan_interest',
            'paid_from_previous',
            'deducted_from_new_loan',
            'loan_purpose',
        ]);

        $loan->delete();

        SysLogService::record(
            action: 'delete',
            table: 'tbl_employee_loans',
            recordId: $loan->employee_loan_id,
            oldValues: $old,
            description: 'Soft-deleted employee loan for '.$employee->employee_number,
        );

        return redirect()
            ->route('employees.edit', ['employee' => $employee, 'tab' => 'loans'])
            ->with('success', 'Loan removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Employee $employee, ?EmployeeLoan $loan = null): array
    {
        $isEdit = $loan !== null;

        try {
            $validated = $request->validate([
                'loan_type_id' => [
                    'required',
                    'integer',
                    Rule::exists('tbl_loan_types', 'loan_type_id')->whereNull('deleted_at'),
                ],
                'payment_scheme_id' => [
                    'required',
                    'integer',
                    Rule::exists('lu_payment_schemes', 'payment_scheme_id'),
                ],
                'dt_loan' => ['required', 'date'],
                'dt_start_payment' => ['nullable', 'date'],
                'number_of_payments' => [
                    Rule::requiredIf(fn () => (int) $request->input('payment_scheme_id') === PaymentScheme::BASED_ON_NUMBER_OF_PAYMENTS),
                    'nullable',
                    'integer',
                    'min:1',
                    'max:999',
                ],
                'loan_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
                'amortization_amount' => [
                    Rule::requiredIf(fn () => (int) $request->input('payment_scheme_id') === PaymentScheme::USER_ENTERED_AMORTIZATION),
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:99999999.99',
                ],
                'loan_interest' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
                'paid_from_previous' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
                'deducted_from_new_loan' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
                'loan_purpose' => ['nullable', 'string', 'max:150'],
            ]);
        } catch (ValidationException $exception) {
            $params = [
                'employee' => $employee,
                'tab' => 'loans',
            ];

            if ($isEdit) {
                $params['edit_loan'] = $loan->employee_loan_id;
            } else {
                $params['create'] = 1;
            }

            throw $exception->redirectTo(route('employees.edit', $params));
        }

        $paymentSchemeId = (int) $validated['payment_scheme_id'];
        $loanAmount = round((float) $validated['loan_amount'], 2);
        $loanInterest = isset($validated['loan_interest']) && $validated['loan_interest'] !== null
            ? round((float) $validated['loan_interest'], 2)
            : null;
        $paidFromPrevious = round((float) ($validated['paid_from_previous'] ?? 0), 2);
        $deductedFromNewLoan = round((float) ($validated['deducted_from_new_loan'] ?? 0), 2);
        $numberOfPayments = $paymentSchemeId === PaymentScheme::BASED_ON_NUMBER_OF_PAYMENTS
            ? (int) $validated['number_of_payments']
            : null;
        $amortizationAmount = EmployeeLoan::computeAmortization(
            $paymentSchemeId,
            $loanAmount,
            $numberOfPayments,
            isset($validated['amortization_amount']) ? (float) $validated['amortization_amount'] : null,
        );

        $balance = round(
            $loanAmount + (float) ($loanInterest ?? 0) - $paidFromPrevious - $deductedFromNewLoan,
            2
        );

        if ($balance < 0) {
            throw ValidationException::withMessages([
                'loan_amount' => 'Loan balance cannot be negative.',
            ])->redirectTo(route('employees.edit', array_filter([
                'employee' => $employee,
                'tab' => 'loans',
                'edit_loan' => $isEdit ? $loan->employee_loan_id : null,
                'create' => $isEdit ? null : 1,
            ])));
        }

        return [
            'loan_type_id' => (int) $validated['loan_type_id'],
            'payment_scheme_id' => $paymentSchemeId,
            'dt_loan' => $validated['dt_loan'],
            'dt_start_payment' => $validated['dt_start_payment'] ?: null,
            'number_of_payments' => $numberOfPayments,
            'loan_amount' => $loanAmount,
            'principal_loan_amount' => EmployeeLoan::computePrincipal($loanAmount, $loanInterest),
            'amortization_amount' => $amortizationAmount,
            'loan_interest' => $loanInterest,
            'paid_from_previous' => $paidFromPrevious,
            'deducted_from_new_loan' => $deductedFromNewLoan,
            'loan_purpose' => filled($validated['loan_purpose'] ?? null)
                ? trim((string) $validated['loan_purpose'])
                : null,
        ];
    }

    private function ensureBelongsToEmployee(Employee $employee, EmployeeLoan $loan): void
    {
        if ((int) $loan->employee_id !== (int) $employee->employee_id) {
            abort(404);
        }
    }
}
