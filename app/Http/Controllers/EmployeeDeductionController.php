<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Services\SysLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeDeductionController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $validated = $this->validated($request, $employee);

        $deduction = EmployeeDeduction::query()->create([
            'employee_id' => $employee->employee_id,
            ...$validated,
        ]);

        SysLogService::record(
            action: 'create',
            table: 'tbl_employee_deductions',
            recordId: $deduction->employee_deduction_id,
            newValues: $deduction->only([
                'employee_id',
                'deduction_type_id',
                'dt_deduction',
                'deduction_amount',
                'amortization_amount',
                'deduction_interest',
            ]),
            description: 'Added employee deduction for '.$employee->employee_number,
        );

        return redirect()
            ->route('employees.edit', ['employee' => $employee, 'tab' => 'deductions'])
            ->with('success', 'Deduction added.');
    }

    public function update(Request $request, Employee $employee, EmployeeDeduction $employeeDeduction): RedirectResponse
    {
        $this->authorize('update', $employee);
        $this->ensureBelongsToEmployee($employee, $employeeDeduction);

        $validated = $this->validated($request, $employee, $employeeDeduction);

        $old = $employeeDeduction->only([
            'deduction_type_id',
            'dt_deduction',
            'deduction_amount',
            'amortization_amount',
            'deduction_interest',
        ]);

        $employeeDeduction->update($validated);

        SysLogService::record(
            action: 'update',
            table: 'tbl_employee_deductions',
            recordId: $employeeDeduction->employee_deduction_id,
            oldValues: $old,
            newValues: $employeeDeduction->fresh()?->only(array_keys($old)),
            description: 'Updated employee deduction for '.$employee->employee_number,
        );

        return redirect()
            ->route('employees.edit', ['employee' => $employee, 'tab' => 'deductions'])
            ->with('success', 'Deduction updated.');
    }

    public function destroy(Employee $employee, EmployeeDeduction $employeeDeduction): RedirectResponse
    {
        $this->authorize('update', $employee);
        $this->ensureBelongsToEmployee($employee, $employeeDeduction);

        $old = $employeeDeduction->only([
            'deduction_type_id',
            'dt_deduction',
            'deduction_amount',
            'amortization_amount',
            'deduction_interest',
        ]);

        $employeeDeduction->delete();

        SysLogService::record(
            action: 'delete',
            table: 'tbl_employee_deductions',
            recordId: $employeeDeduction->employee_deduction_id,
            oldValues: $old,
            description: 'Soft-deleted employee deduction for '.$employee->employee_number,
        );

        return redirect()
            ->route('employees.edit', ['employee' => $employee, 'tab' => 'deductions'])
            ->with('success', 'Deduction removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Employee $employee, ?EmployeeDeduction $deduction = null): array
    {
        $isEdit = $deduction !== null;
        $redirect = function () use ($employee, $isEdit, $deduction): string {
            return route('employees.edit', array_filter([
                'employee' => $employee,
                'tab' => 'deductions',
                'edit_deduction' => $isEdit ? $deduction->employee_deduction_id : null,
            ]));
        };

        try {
            $validated = $request->validate([
                'deduction_type_id' => [
                    'required',
                    'integer',
                    Rule::exists('tbl_deduction_types', 'deduction_type_id')->whereNull('deleted_at'),
                ],
                'dt_deduction' => ['required', 'date'],
                'deduction_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
                'amortization_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
                'deduction_interest' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            ]);
        } catch (ValidationException $exception) {
            throw $exception->redirectTo($redirect());
        }

        $typeId = (int) $validated['deduction_type_id'];

        if (! EmployeeDeduction::selectableTypesQuery()->whereKey($typeId)->exists()) {
            throw ValidationException::withMessages([
                'deduction_type_id' => 'Choose a deduction type that is not a government premium or an attendance deduction.',
            ])->redirectTo($redirect());
        }

        $amount = round((float) $validated['deduction_amount'], 2);
        $interest = round((float) ($validated['deduction_interest'] ?? 0), 2);
        $amortization = round((float) $validated['amortization_amount'], 2);
        $alreadyTaken = $isEdit ? $deduction->payrollDeductionsTotal() : 0.0;
        $balance = round($amount + $interest - $alreadyTaken, 2);

        if ($balance < 0) {
            throw ValidationException::withMessages([
                'deduction_amount' => 'Deduction balance cannot be negative. Amount plus interest must cover what payroll already deducted.',
            ])->redirectTo($redirect());
        }

        return [
            'deduction_type_id' => $typeId,
            'dt_deduction' => $validated['dt_deduction'],
            'deduction_amount' => $amount,
            'amortization_amount' => $amortization,
            'deduction_interest' => $interest,
        ];
    }

    private function ensureBelongsToEmployee(Employee $employee, EmployeeDeduction $deduction): void
    {
        if ((int) $deduction->employee_id !== (int) $employee->employee_id) {
            abort(404);
        }
    }
}
