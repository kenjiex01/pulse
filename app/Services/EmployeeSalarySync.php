<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\EmployeeSalary;

class EmployeeSalarySync
{
    public static function sync(Employee $employee, array $salaryRecords, bool $isHybrid): void
    {
        $employee->unsetRelation('employmentInformations');
        $employee->load('employmentInformations.salary.incomes', 'employmentInformations.salary.deductions');

        $employmentRecords = $employee->employmentInformations->values();
        $expectedCount = $isHybrid ? 2 : 1;

        foreach ($employmentRecords as $index => $employmentInfo) {
            if ($index >= $expectedCount) {
                $employmentInfo->salary?->forceDelete();

                continue;
            }

            $salaryData = collect($salaryRecords)->first(
                fn ($record) => (int) ($record['employment_index'] ?? -1) === $index,
            ) ?? ($salaryRecords[$index] ?? null);

            if (! is_array($salaryData)) {
                $employmentInfo->salary?->forceDelete();

                continue;
            }

            self::syncSalaryForEmployment($employmentInfo, $salaryData);
        }
    }

    private static function syncSalaryForEmployment(EmployeeEmploymentInformation $employmentInfo, array $salaryData): void
    {
        $salary = EmployeeSalary::query()->updateOrCreate(
            ['employment_info_id' => $employmentInfo->employment_info_id],
            [
                'date_effective' => $salaryData['date_effective'] ?? null,
                'basic_computation_id' => $salaryData['basic_computation_id'] ?? null,
                'pay_type_id' => $salaryData['pay_type_id'] ?? null,
                'days_per_period' => $salaryData['days_per_period'] ?? null,
                'hours_per_day' => $salaryData['hours_per_day'] ?? null,
                'use_basic_income_as_hourly_rate' => ! empty($salaryData['use_basic_income_as_hourly_rate']),
                'rate_group_id' => $salaryData['rate_group_id'] ?? null,
                'nd_rate_group_id' => $salaryData['nd_rate_group_id'] ?? null,
            ],
        );

        $salary->incomes()->forceDelete();
        foreach (array_values($salaryData['incomes'] ?? []) as $incomeIndex => $income) {
            if (! is_array($income) || blank($income['income_type_id'] ?? null)) {
                continue;
            }

            $salary->incomes()->create([
                'income_type_id' => $income['income_type_id'],
                'taxable' => $income['taxable'] ?? 0,
                'non_taxable' => $income['non_taxable'] ?? 0,
                'sort_order' => $incomeIndex,
            ]);
        }

        $salary->deductions()->forceDelete();
        foreach (array_values($salaryData['deductions'] ?? []) as $deductionIndex => $deduction) {
            if (! is_array($deduction) || blank($deduction['deduction_type_id'] ?? null)) {
                continue;
            }

            $salary->deductions()->create([
                'deduction_type_id' => $deduction['deduction_type_id'],
                'employee_amount' => $deduction['employee_amount'] ?? 0,
                'employer_amount' => $deduction['employer_amount'] ?? 0,
                'sort_order' => $deductionIndex,
            ]);
        }
    }

    public static function normalizeRecords(array $records, bool $isHybrid): array
    {
        $records = array_values(array_filter($records, fn ($record) => is_array($record)));
        $expectedCount = $isHybrid ? 2 : 1;

        while (count($records) < $expectedCount) {
            $records[] = ['employment_index' => count($records)];
        }

        return array_slice(array_map(function (array $record, int $index) {
            $record['employment_index'] = $index;

            return $record;
        }, $records, array_keys($records)), 0, $expectedCount);
    }
}
