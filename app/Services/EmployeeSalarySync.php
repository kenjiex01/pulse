<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\EmployeeSalary;
use Carbon\Carbon;

class EmployeeSalarySync
{
    public static function sync(Employee $employee, array $salaryRecords, bool $isHybrid): void
    {
        $employee->unsetRelation('employmentInformations');
        $employee->load([
            'employmentInformations.salary.incomes',
            'employmentInformations.salary.deductions',
            'employmentInformations.salaries.incomes',
            'employmentInformations.salaries.deductions',
        ]);

        $employmentRecords = $employee->employmentInformations
            ->sortBy([
                ['sort_order', 'asc'],
                ['employment_info_id', 'asc'],
            ])
            ->values();
        $expectedCount = $isHybrid ? 2 : 1;

        foreach ($employmentRecords as $index => $employmentInfo) {
            if ($index >= $expectedCount) {
                // Soft-delete only — never wipe salary history with forceDelete on trim.
                $employmentInfo->salaries()->each(fn (EmployeeSalary $salary) => $salary->delete());

                continue;
            }

            $salaryData = collect($salaryRecords)->first(
                fn ($record) => (int) ($record['employment_index'] ?? -1) === $index,
            ) ?? ($salaryRecords[$index] ?? null);

            if (! is_array($salaryData)) {
                continue;
            }

            self::syncSalaryForEmployment($employmentInfo, $salaryData);
        }
    }

    public static function syncForEmployment(EmployeeEmploymentInformation $employmentInfo, array $salaryData): void
    {
        self::syncSalaryForEmployment($employmentInfo, $salaryData);
    }

    private static function syncSalaryForEmployment(EmployeeEmploymentInformation $employmentInfo, array $salaryData): void
    {
        $current = EmployeeSalary::query()
            ->where('employment_info_id', $employmentInfo->employment_info_id)
            ->whereNull('date_effective_to')
            ->orderByDesc('date_effective_from')
            ->orderByDesc('employee_salary_id')
            ->first();

        $effectiveFrom = self::normalizeDate(
            $salaryData['date_effective_from']
                ?? $salaryData['date_effective']
                ?? now()->toDateString(),
        );

        $headerPayload = self::headerPayload($salaryData, $effectiveFrom);
        $incomeRows = self::incomeRows($salaryData);
        $deductionRows = self::deductionRows($salaryData);

        if ($current && self::shouldCreateSalaryHistory($current, $headerPayload, $incomeRows, $deductionRows)) {
            $currentFrom = $current->date_effective_from?->toDateString();
            $closeDate = Carbon::parse($effectiveFrom)->subDay()->toDateString();

            if ($currentFrom !== null && $currentFrom <= $closeDate) {
                // New later effectivity date — close previous day before the new from-date.
                $current->update(['date_effective_to' => $closeDate]);
            } elseif ($currentFrom !== null && $currentFrom === $effectiveFrom) {
                // Same effectivity date but salary content changed (e.g. 25000 → 25000.01).
                // Never force-delete: archive the old row as previous (from = to) and insert a new current.
                $current->update(['date_effective_to' => $currentFrom]);
            } else {
                // New from is earlier than the open current — keep history by closing the open row
                // on its own start date, then create the earlier/new current row.
                $current->update([
                    'date_effective_to' => $currentFrom ?? $effectiveFrom,
                ]);
            }

            $current = null;
        }

        $salary = $current ?? new EmployeeSalary([
            'employment_info_id' => $employmentInfo->employment_info_id,
        ]);

        $salary->fill($headerPayload);
        $salary->save();

        self::syncIncomeRows($salary, $incomeRows);
        self::syncDeductionRows($salary, $deductionRows);
    }

    /**
     * @param  array<string, mixed>  $salaryData
     * @return array<string, mixed>
     */
    private static function headerPayload(array $salaryData, string $effectiveFrom): array
    {
        return [
            'date_effective_from' => $effectiveFrom,
            'date_effective_to' => filled($salaryData['date_effective_to'] ?? null)
                ? self::normalizeDate($salaryData['date_effective_to'])
                : null,
            'basic_computation_id' => $salaryData['basic_computation_id'] ?? null,
            'pay_type_id' => $salaryData['pay_type_id'] ?? null,
            'days_per_period' => $salaryData['days_per_period'] ?? null,
            'hours_per_day' => $salaryData['hours_per_day'] ?? null,
            'use_basic_income_as_hourly_rate' => ! empty($salaryData['use_basic_income_as_hourly_rate']),
            'is_above_minimum_wage_earner' => ! empty($salaryData['is_above_minimum_wage_earner']),
            'rate_group_id' => $salaryData['rate_group_id'] ?? null,
            'nd_rate_group_id' => $salaryData['nd_rate_group_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $salaryData
     * @return array<int, array<string, mixed>>
     */
    private static function incomeRows(array $salaryData): array
    {
        $rows = [];

        foreach (array_values($salaryData['incomes'] ?? []) as $incomeIndex => $income) {
            if (! is_array($income) || blank($income['income_type_id'] ?? null)) {
                continue;
            }

            $rows[] = [
                'income_type_id' => (int) $income['income_type_id'],
                'taxable' => (float) ($income['taxable'] ?? 0),
                'non_taxable' => (float) ($income['non_taxable'] ?? 0),
                'sort_order' => $incomeIndex,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $salaryData
     * @return array<int, array<string, mixed>>
     */
    private static function deductionRows(array $salaryData): array
    {
        $rows = [];

        foreach (array_values($salaryData['deductions'] ?? []) as $deductionIndex => $deduction) {
            if (! is_array($deduction) || blank($deduction['deduction_type_id'] ?? null)) {
                continue;
            }

            $rows[] = [
                'deduction_type_id' => (int) $deduction['deduction_type_id'],
                'employee_amount' => (float) ($deduction['employee_amount'] ?? 0),
                'employer_amount' => (float) ($deduction['employer_amount'] ?? 0),
                'sort_order' => $deductionIndex,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $headerPayload
     * @param  array<int, array<string, mixed>>  $incomeRows
     * @param  array<int, array<string, mixed>>  $deductionRows
     */
    private static function shouldCreateSalaryHistory(
        EmployeeSalary $current,
        array $headerPayload,
        array $incomeRows,
        array $deductionRows,
    ): bool {
        if (($current->date_effective_from?->toDateString() ?? '') !== ($headerPayload['date_effective_from'] ?? '')) {
            return true;
        }

        foreach ([
            'basic_computation_id',
            'pay_type_id',
            'days_per_period',
            'hours_per_day',
            'use_basic_income_as_hourly_rate',
            'is_above_minimum_wage_earner',
            'rate_group_id',
            'nd_rate_group_id',
        ] as $field) {
            $currentValue = $current->{$field};
            $nextValue = $headerPayload[$field] ?? null;

            if (in_array($field, ['use_basic_income_as_hourly_rate', 'is_above_minimum_wage_earner'], true)) {
                if ((bool) $currentValue !== (bool) $nextValue) {
                    return true;
                }

                continue;
            }

            if ((string) ($currentValue ?? '') !== (string) ($nextValue ?? '')) {
                return true;
            }
        }

        return self::normalizedIncomeRows($current) !== $incomeRows
            || self::normalizedDeductionRows($current) !== $deductionRows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizedIncomeRows(EmployeeSalary $salary): array
    {
        return $salary->incomes
            ->map(fn ($income) => [
                'income_type_id' => (int) $income->income_type_id,
                'taxable' => (float) $income->taxable,
                'non_taxable' => (float) $income->non_taxable,
                'sort_order' => (int) $income->sort_order,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizedDeductionRows(EmployeeSalary $salary): array
    {
        return $salary->deductions
            ->map(fn ($deduction) => [
                'deduction_type_id' => (int) $deduction->deduction_type_id,
                'employee_amount' => (float) $deduction->employee_amount,
                'employer_amount' => (float) $deduction->employer_amount,
                'sort_order' => (int) $deduction->sort_order,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $incomeRows
     */
    private static function syncIncomeRows(EmployeeSalary $salary, array $incomeRows): void
    {
        $salary->incomes()->forceDelete();

        foreach ($incomeRows as $row) {
            $salary->incomes()->create($row);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $deductionRows
     */
    private static function syncDeductionRows(EmployeeSalary $salary, array $deductionRows): void
    {
        $salary->deductions()->forceDelete();

        foreach ($deductionRows as $row) {
            $salary->deductions()->create($row);
        }
    }

    private static function normalizeDate(mixed $value): string
    {
        return Carbon::parse((string) $value)->toDateString();
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
