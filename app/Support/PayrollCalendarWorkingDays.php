<?php

namespace App\Support;

use App\Models\EmployeeSalary;
use App\Models\LuDay;
use App\Models\TimekeepingEmployeeRestDay;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Working days in a payroll calendar cutoff: every date from dt_from through dt_to
 * except the employee's timekeeping rest days.
 */
class PayrollCalendarWorkingDays
{
    /** @var array<int, list<int>> */
    private static array $restDayIdsByEmployee = [];

    /**
     * @param  list<int>  $restDayIds
     */
    public static function count(CarbonInterface $from, CarbonInterface $to, array $restDayIds = []): int
    {
        $start = CarbonImmutable::parse($from->toDateString())->startOfDay();
        $end = CarbonImmutable::parse($to->toDateString())->startOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        $rest = array_fill_keys(array_map('intval', $restDayIds), true);
        $count = 0;
        $cursor = $start;

        while ($cursor->lte($end)) {
            if (! isset($rest[LuDay::idFromDate($cursor)])) {
                $count++;
            }

            $cursor = $cursor->addDay();
        }

        return $count;
    }

    /**
     * Day count used as the payroll divisor when the salary flag is on.
     * Null keeps the stored Days Per Period.
     */
    public static function forEmployeeSalary(
        EmployeeSalary $salary,
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
    ): ?float {
        if (! $salary->use_payroll_calendar_days || $employeeId <= 0) {
            return null;
        }

        $count = self::count($from, $to, self::restDayIdsForEmployee($employeeId));

        return $count > 0 ? (float) $count : null;
    }

    /**
     * @return list<int>
     */
    public static function restDayIdsForEmployee(int $employeeId): array
    {
        if ($employeeId <= 0) {
            return [];
        }

        if (! array_key_exists($employeeId, self::$restDayIdsByEmployee)) {
            self::$restDayIdsByEmployee[$employeeId] = TimekeepingEmployeeRestDay::query()
                ->where('employee_id', $employeeId)
                ->pluck('day_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return self::$restDayIdsByEmployee[$employeeId];
    }

    public static function isRestDay(int $employeeId, CarbonInterface $date): bool
    {
        $restDayIds = self::restDayIdsForEmployee($employeeId);

        if ($restDayIds === []) {
            return false;
        }

        return in_array(LuDay::idFromDate($date), $restDayIds, true);
    }

    /**
     * Dates in the cutoff with no attendance. Rest days are never absences.
     *
     * @param  list<int>  $restDayIds
     * @param  list<string>  $workedDates
     * @return list<string>
     */
    public static function absenceDates(
        CarbonInterface $from,
        CarbonInterface $to,
        array $restDayIds,
        array $workedDates,
    ): array {
        $start = CarbonImmutable::parse($from->toDateString())->startOfDay();
        $end = CarbonImmutable::parse($to->toDateString())->startOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        $rest = array_fill_keys(array_map('intval', $restDayIds), true);
        $worked = array_fill_keys($workedDates, true);
        $dates = [];
        $cursor = $start;

        while ($cursor->lte($end)) {
            $isRestDay = isset($rest[LuDay::idFromDate($cursor)]);

            if (! $isRestDay && ! isset($worked[$cursor->toDateString()])) {
                $dates[] = $cursor->toDateString();
            }

            $cursor = $cursor->addDay();
        }

        return $dates;
    }

    /**
     * Full working days in the cutoff that were not worked. Half-days stay in the
     * existing half-day absence amount because those days are already counted as worked.
     * Rest days are not absences.
     *
     * When the cutoff dates are passed, the day count is the dates themselves so a
     * rest day cannot become an absence even if it has no punch.
     *
     * @param  list<int>  $restDayIds
     * @param  list<string>  $workedDates
     * @return array{days: float, amount: float}
     */
    public static function unworkedAbsence(
        EmployeeSalary $salary,
        ?float $rateDays,
        float $workedDays,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        array $restDayIds = [],
        array $workedDates = [],
    ): array {
        if (! $salary->use_payroll_calendar_days || $rateDays === null || $rateDays <= 0) {
            return ['days' => 0.0, 'amount' => 0.0];
        }

        $days = ($from !== null && $to !== null)
            ? (float) count(self::absenceDates($from, $to, $restDayIds, $workedDates))
            : max(0.0, $rateDays - $workedDays);
        $dailyRate = $salary->basicIncomeAmount() / $rateDays;

        return [
            'days' => $days,
            'amount' => round($days * $dailyRate, 2),
        ];
    }
}
