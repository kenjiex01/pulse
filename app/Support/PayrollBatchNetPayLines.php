<?php

namespace App\Support;

use Illuminate\Support\Collection;

class PayrollBatchNetPayLines
{
    /**
     * @param  Collection<int, mixed>  $incomes
     * @return Collection<int, array{label: string, hours: ?float, days: ?float, amount: float}>
     */
    public static function incomeLines(Collection $incomes): Collection
    {
        return $incomes
            ->sortBy(fn ($income) => $income->incomeType?->income_type_code ?? '')
            ->map(fn ($income) => [
                'label' => $income->incomeType?->description ?? $income->incomeType?->income_type_code ?? 'Income',
                'hours' => $income->hours !== null ? (float) $income->hours : null,
                'days' => $income->days !== null ? (float) $income->days : null,
                'amount' => round((float) $income->taxable + (float) $income->non_taxable, 2),
            ])
            ->filter(fn (array $line) => $line['amount'] !== 0.0)
            ->values();
    }

    /**
     * @param  Collection<int, mixed>  $deductions
     * @return Collection<int, array{code: ?string, description: ?string, hours: ?float, show_hours: bool, minutes: ?int, days: ?float, show_days: bool, employee_amount: float, employer_amount: float}>
     */
    public static function deductionRows(Collection $deductions): Collection
    {
        return $deductions
            ->groupBy(fn ($deduction) => (int) $deduction->deduction_type_id)
            ->map(function ($group) {
                $first = $group->first();
                $code = $first->deductionType?->deduction_type_code;
                $hoursSum = $group->sum(fn ($deduction) => (float) ($deduction->hours ?? 0));
                $daysSum = $group->sum(fn ($deduction) => (float) ($deduction->days ?? 0));
                $hasHours = in_array($code, ['LTDE', 'UTDE'], true)
                    && $group->contains(fn ($deduction) => $deduction->hours !== null);
                $hasDays = in_array($code, ['LTDE', 'UTDE'], true)
                    && $group->contains(fn ($deduction) => $deduction->days !== null);

                return [
                    'code' => $code,
                    'description' => PhilhealthDeductionTypes::payrollBatchLabel($code, $first->deductionType?->description),
                    'hours' => $hasHours ? $hoursSum : null,
                    'show_hours' => $hasHours,
                    'minutes' => $hasHours ? (int) round($hoursSum * 60) : null,
                    'days' => $hasDays ? $daysSum : null,
                    'show_days' => $hasDays,
                    'employee_amount' => $group->sum(fn ($deduction) => (float) $deduction->employee_amount),
                    'employer_amount' => $group->sum(fn ($deduction) => (float) $deduction->employer_amount),
                ];
            })
            ->sortBy('code')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $deductionRows
     * @return array<int, array{label: string, minutes: ?int, amount: float}>
     */
    public static function payslipDeductionLines(Collection $deductionRows): array
    {
        return $deductionRows
            ->map(fn (array $deduction) => [
                'label' => $deduction['description'] ?? $deduction['code'] ?? 'Deduction',
                'minutes' => ($deduction['minutes'] ?? null) !== null ? (int) $deduction['minutes'] : null,
                'amount' => round((float) $deduction['employee_amount'], 2),
            ])
            ->filter(fn (array $line) => $line['amount'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{label: string, hours: ?float, days: ?float, amount: float}>  $incomeLines
     * @return array<int, array{label: string, hours: ?float, days: ?float, amount: float}>
     */
    public static function payslipEarningsLines(Collection $incomeLines): array
    {
        return $incomeLines
            ->map(fn (array $line) => [
                'label' => $line['label'],
                'hours' => $line['hours'],
                'days' => $line['days'],
                'amount' => $line['amount'],
            ])
            ->values()
            ->all();
    }
}
