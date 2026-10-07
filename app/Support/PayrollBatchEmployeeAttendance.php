<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollCalendar;
use App\Models\RawEmployeeLoadEntry;
use Carbon\CarbonInterface;

class PayrollBatchEmployeeAttendance
{
    public static function isBelowHalfPayrollPeriod(PayrollBatchDetail $detail): bool
    {
        $detail->loadMissing('employee');

        if ($detail->employee?->isFaculty()) {
            $expectedDays = self::expectedFacultyDays($detail);
            $workedDays = self::facultyWorkedDays($detail);

            if ($expectedDays === null || $expectedDays <= 0) {
                return $workedDays <= 0;
            }

            return $workedDays < ($expectedDays / 2);
        }

        $expected = self::expectedPeriodUnits($detail);

        if ($expected === null || $expected <= 0) {
            return false;
        }

        return self::workedPeriodUnits($detail) < ($expected / 2);
    }

    /**
     * Teaching-load days in the cutoff (BASC days).
     */
    public static function facultyWorkedDays(PayrollBatchDetail $detail): float
    {
        $detail->loadMissing(['incomes.incomeType', 'attendanceDays']);

        $bascDays = $detail->incomes
            ->filter(fn ($income) => strtoupper((string) ($income->incomeType?->income_type_code ?? '')) === 'BASC')
            ->sum(fn ($income) => (float) ($income->days ?? 0));

        if ($bascDays > 0) {
            return round($bascDays, 4);
        }

        return (float) $detail->attendanceDays
            ->filter(fn ($day) => (float) ($day->basic ?? 0) > 0)
            ->count();
    }

    /**
     * Faculty only. Same count as Skolaris Employee Attendance "Days":
     * distinct class dates with a teaching load inside the pay period.
     */
    public static function expectedFacultyDays(PayrollBatchDetail $detail): ?float
    {
        $detail->loadMissing(['employee', 'payrollBatch.payrollCalendar']);

        $employee = $detail->employee;
        $calendar = $detail->payrollBatch?->payrollCalendar;

        if ($employee === null || $calendar?->dt_from === null || $calendar->dt_to === null) {
            return null;
        }

        $query = RawEmployeeLoadEntry::query()
            ->whereBetween('session_date', [
                $calendar->dt_from->toDateString(),
                $calendar->dt_to->toDateString(),
            ])
            ->whereNotNull('session_date')
            ->where(function ($inner) use ($employee) {
                $inner->where('employee_id', $employee->employee_id);

                if (filled($employee->employee_number)) {
                    $inner->orWhere(function ($fallback) use ($employee) {
                        $fallback
                            ->whereNull('employee_id')
                            ->where('employee_number', $employee->employee_number);
                    });
                }
            });

        $days = (int) $query->selectRaw('count(distinct session_date) as days')->value('days');

        return $days > 0 ? (float) $days : null;
    }

    public static function workedPeriodUnits(PayrollBatchDetail $detail): float
    {
        $detail->loadMissing(['incomes.incomeType', 'attendanceDays', 'employee']);

        $employee = $detail->employee;
        $isFaculty = $employee?->isFaculty() ?? false;

        $bascIncomes = $detail->incomes
            ->filter(fn ($income) => strtoupper((string) ($income->incomeType?->income_type_code ?? '')) === 'BASC');

        if ($isFaculty) {
            $hours = $bascIncomes->sum(fn ($income) => (float) ($income->hours ?? 0));

            if ($hours <= 0) {
                $hours = (float) $detail->attendanceDays->sum(fn ($day) => (float) ($day->basic ?? 0));
            }

            return round(max(0, $hours), 4);
        }

        $days = $bascIncomes->sum(fn ($income) => (float) ($income->days ?? 0));

        if ($days <= 0) {
            $days = (float) $detail->attendanceDays
                ->filter(fn ($day) => (float) ($day->basic ?? 0) > 0)
                ->count();
        }

        return round(max(0, $days), 4);
    }

    public static function expectedPeriodUnits(PayrollBatchDetail $detail): ?float
    {
        $detail->loadMissing([
            'employee.employmentInformations.salary',
            'employee.employmentInformations.salaries',
            'employee.timekeepingRestDays',
            'payrollBatch.payrollCalendar',
        ]);

        $employee = $detail->employee;
        $calendar = $detail->payrollBatch?->payrollCalendar;

        if ($employee === null || $calendar === null) {
            return null;
        }

        $salary = self::resolveSalary($employee, $calendar);

        if ($calendar->dt_from !== null && $calendar->dt_to !== null) {
            $workingDays = self::workingDaysInPeriod($employee, $calendar);

            if ($employee->isFaculty()) {
                $hoursPerDay = (float) ($salary?->hours_per_day ?? 0);

                if ($workingDays > 0 && $hoursPerDay > 0) {
                    return round($workingDays * $hoursPerDay, 4);
                }
            } elseif ($workingDays > 0) {
                return round($workingDays, 4);
            }
        }

        $daysPerPeriod = (float) ($salary?->days_per_period ?? 0);

        if ($employee->isFaculty()) {
            $hoursPerDay = (float) ($salary?->hours_per_day ?? 0);

            if ($daysPerPeriod > 0 && $hoursPerDay > 0) {
                return round($daysPerPeriod * $hoursPerDay, 4);
            }
        } elseif ($daysPerPeriod > 0) {
            return round($daysPerPeriod, 4);
        }

        return null;
    }

    public static function workingDaysInPeriod(Employee $employee, PayrollCalendar $calendar): float
    {
        if ($calendar->dt_from === null || $calendar->dt_to === null) {
            return 0.0;
        }

        $calendarDays = (float) ($calendar->dt_from->diffInDays($calendar->dt_to) + 1);
        $restDays = (float) self::restDaysInPeriod($employee, $calendar);

        return max(0.0, $calendarDays - $restDays);
    }

    public static function restDaysInPeriod(Employee $employee, PayrollCalendar $calendar): int
    {
        if ($calendar->dt_from === null || $calendar->dt_to === null) {
            return 0;
        }

        $employee->loadMissing('timekeepingRestDays');

        $restDayIds = $employee->timekeepingRestDays
            ->pluck('day_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($restDayIds === []) {
            return 0;
        }

        $count = 0;
        $cursor = $calendar->dt_from->copy()->startOfDay();
        $end = $calendar->dt_to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            if (in_array(self::dayIdForDate($cursor), $restDayIds, true)) {
                $count++;
            }

            $cursor = $cursor->addDay();
        }

        return $count;
    }

    private static function dayIdForDate(CarbonInterface $date): int
    {
        return (int) $date->dayOfWeek + 1;
    }

    private static function resolveSalary(Employee $employee, PayrollCalendar $calendar): ?EmployeeSalary
    {
        $employment = $employee->employmentInformations
            ?->sortBy('sort_order')
            ->first();

        if ($employment === null) {
            return null;
        }

        if ($employment->relationLoaded('salary') && $employment->salary !== null) {
            return $employment->salary;
        }

        if ($employment->relationLoaded('salaries') && $employment->salaries->isNotEmpty()) {
            return $employment->salaries
                ->sortByDesc(fn (EmployeeSalary $salary) => $salary->date_effective_from?->format('Y-m-d') ?? '')
                ->first();
        }

        return $employment->salary ?? $employment->salaries()->first();
    }
}
