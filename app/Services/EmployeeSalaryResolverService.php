<?php

namespace App\Services;

use App\Models\EmployeeSalary;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EmployeeSalaryResolverService
{
    /**
     * @return Collection<int, EmployeeSalary>
     */
    public function salariesForPeriod(
        int $employeeId,
        int $payTypeId,
        CarbonInterface $from,
        CarbonInterface $to,
    ): Collection {
        return $this->baseSalariesForPeriodQuery($employeeId, $payTypeId, $from, $to)->get();
    }

    /**
     * Salaries for one employment user type (faculty / staff / admin) in the pay period.
     *
     * @return Collection<int, EmployeeSalary>
     */
    public function salariesForPeriodByUserType(
        int $employeeId,
        int $payTypeId,
        CarbonInterface $from,
        CarbonInterface $to,
        string $userType,
    ): Collection {
        return $this->baseSalariesForPeriodQuery($employeeId, $payTypeId, $from, $to)
            ->whereHas('employmentInformation', fn ($query) => $query->where('user_type', $userType))
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<EmployeeSalary>
     */
    private function baseSalariesForPeriodQuery(
        int $employeeId,
        int $payTypeId,
        CarbonInterface $from,
        CarbonInterface $to,
    ) {
        return EmployeeSalary::query()
            ->where('pay_type_id', $payTypeId)
            ->whereHas('employmentInformation', fn ($query) => $query->where('employee_id', $employeeId))
            ->where('date_effective_from', '<=', $to->toDateString())
            ->where(function ($query) use ($from) {
                $query->whereNull('date_effective_to')
                    ->orWhere('date_effective_to', '>=', $from->toDateString());
            })
            ->with(['incomes.incomeType', 'deductions.deductionType', 'employmentInformation'])
            ->orderBy('date_effective_from')
            ->orderBy('employee_salary_id');
    }

    /**
     * @param  Collection<int, EmployeeSalary>  $salaries
     */
    public function salaryEffectiveOnDate(Collection $salaries, CarbonInterface $date): ?EmployeeSalary
    {
        $dateString = $date->toDateString();

        return $salaries
            ->filter(function (EmployeeSalary $salary) use ($dateString) {
                $from = $salary->date_effective_from?->toDateString();
                $to = $salary->date_effective_to?->toDateString();

                if ($from === null || $from > $dateString) {
                    return false;
                }

                return $to === null || $to >= $dateString;
            })
            ->sortByDesc(fn (EmployeeSalary $salary) => $salary->date_effective_from?->toDateString())
            ->sortByDesc('employee_salary_id')
            ->first();
    }

    public function prorateAmount(
        float $amount,
        EmployeeSalary $salary,
        CarbonInterface $periodFrom,
        CarbonInterface $periodTo,
    ): float {
        if ($amount <= 0) {
            return 0.0;
        }

        $overlapDays = $this->overlapDayCount($salary, $periodFrom, $periodTo);

        if ($overlapDays <= 0) {
            return 0.0;
        }

        $periodDays = $periodFrom->diffInDays($periodTo) + 1;

        if ($periodDays <= 0) {
            return 0.0;
        }

        return round($amount * ($overlapDays / $periodDays), 2);
    }

    public function overlapDayCount(
        EmployeeSalary $salary,
        CarbonInterface $periodFrom,
        CarbonInterface $periodTo,
    ): int {
        $salaryFrom = $salary->date_effective_from?->startOfDay();
        $salaryTo = $salary->date_effective_to?->endOfDay() ?? $periodTo->copy()->endOfDay();

        if ($salaryFrom === null) {
            return 0;
        }

        $overlapStart = $periodFrom->greaterThan($salaryFrom) ? $periodFrom->copy()->startOfDay() : $salaryFrom;
        $overlapEnd = $periodTo->lessThan($salaryTo) ? $periodTo->copy()->endOfDay() : $salaryTo;

        if ($overlapStart->greaterThan($overlapEnd)) {
            return 0;
        }

        return (int) ($overlapStart->diffInDays($overlapEnd) + 1);
    }
}
