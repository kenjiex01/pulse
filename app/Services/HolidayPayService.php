<?php

namespace App\Services;

use App\Models\DayType;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\IncomeType;
use App\Models\PayrollBatchDetail;
use App\Models\ShiftCode;
use App\Models\TimekeepingHoliday;
use App\Models\TimekeepingHolidayYear;
use App\Models\TimekeepingPolicy;
use App\Support\EmployeePayrollPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class HolidayPayService
{
    private ?int $regularDayTypeId = null;

    /** @var array<string, int> */
    private array $dayTypeCache = [];

    /** @var array<int, list<int>> */
    private array $restDayIdsCache = [];

    public function __construct(
        private readonly TimeLogsPayrollService $timeLogsPayroll,
        private readonly EmployeeLoadPayrollService $employeeLoadPayroll,
        private readonly EmployeeShiftResolver $shiftResolver,
    ) {}

    /**
     * @return Collection<int, object{date: CarbonImmutable, is_legal: bool}>
     */
    public function holidaysForEmployeeInPeriod(Employee $employee, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $employee->loadMissing('timekeepingSetup');
        $groupId = $employee->timekeepingSetup?->timekeeping_holiday_group_id;

        if ($groupId === null) {
            return collect();
        }

        $fromDate = CarbonImmutable::parse($from->toDateString())->startOfDay();
        $toDate = CarbonImmutable::parse($to->toDateString())->startOfDay();

        $employee->loadMissing('timekeepingSetup.holidayGroup.holidays');

        $holidayIds = $employee->timekeepingSetup?->holidayGroup?->holidays
            ?->pluck('timekeeping_holiday_id')
            ->map(fn ($id) => (int) $id)
            ->all() ?? [];

        if ($holidayIds === []) {
            return collect();
        }

        $years = range($fromDate->year, $toDate->year);

        /** @var array<string, object{date: CarbonImmutable, is_legal: bool}> $byDate */
        $byDate = [];

        TimekeepingHolidayYear::query()
            ->whereIn('timekeeping_holiday_id', $holidayIds)
            ->whereHas('year', fn ($query) => $query->whereIn('timekeeping_year', $years))
            ->whereDate('dt_datestamp', '>=', $fromDate->toDateString())
            ->whereDate('dt_datestamp', '<=', $toDate->toDateString())
            ->orderBy('dt_datestamp')
            ->get()
            ->each(function (TimekeepingHolidayYear $entry) use (&$byDate): void {
                $key = $entry->dt_datestamp->toDateString();
                $byDate[$key] = (object) [
                    'date' => CarbonImmutable::parse($key),
                    'is_legal' => (bool) $entry->is_legal,
                ];
            });

        foreach ($employee->timekeepingSetup?->holidayGroup?->holidays ?? [] as $master) {
            if (! $master instanceof TimekeepingHoliday) {
                continue;
            }

            foreach ($this->masterHolidayDatesInRange($master, $fromDate, $toDate) as $date) {
                $key = $date->toDateString();

                if (isset($byDate[$key])) {
                    continue;
                }

                $byDate[$key] = (object) [
                    'date' => $date,
                    'is_legal' => (bool) $master->is_legal,
                ];
            }
        }

        return collect($byDate)
            ->sortBy(fn (object $holiday) => $holiday->date->toDateString())
            ->values();
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function masterHolidayDatesInRange(
        TimekeepingHoliday $master,
        CarbonImmutable $fromDate,
        CarbonImmutable $toDate,
    ): array {
        $base = CarbonImmutable::parse($master->dt_datestamp->toDateString());
        $dates = [];

        if ($master->recurring) {
            foreach (range($fromDate->year, $toDate->year) as $year) {
                try {
                    $occurrence = $base->setDate($year, $base->month, $base->day);
                } catch (\Throwable) {
                    continue;
                }

                if ($occurrence->greaterThanOrEqualTo($fromDate) && $occurrence->lessThanOrEqualTo($toDate)) {
                    $dates[] = $occurrence;
                }
            }

            return $dates;
        }

        if ($base->greaterThanOrEqualTo($fromDate) && $base->lessThanOrEqualTo($toDate)) {
            $dates[] = $base;
        }

        return $dates;
    }

    public function resolveDayTypeId(int $employeeId, CarbonImmutable $date): int
    {
        $holiday = $this->holidayOnDate($employeeId, $date);

        if ($holiday === null) {
            return $this->regularDayTypeId();
        }

        return $this->matchDayTypeId(
            $this->isRestDayForEmployee($employeeId, $date),
            ! $holiday->is_legal,
            $holiday->is_legal,
        );
    }

    public function isRestDayForEmployee(int $employeeId, CarbonImmutable $date): bool
    {
        $restDayIds = $this->restDayIdsForEmployee($employeeId);

        if ($restDayIds === []) {
            return false;
        }

        return in_array($this->dayIdForDate($date), $restDayIds, true);
    }

    public function hasTimeLogOnDate(
        int $employeeId,
        CarbonImmutable $date,
        ?TimekeepingPolicy $policy,
        ?ShiftCode $defaultShift,
    ): bool {
        $sessions = $this->timeLogsPayroll->daySessionsForPeriod($employeeId, $date, $date);

        foreach ($sessions as $session) {
            if ($session['time_in'] === null || $session['time_in'] === '') {
                continue;
            }

            $dayShift = $this->shiftResolver->forDate($employeeId, $date, $defaultShift);

            if ($dayShift !== null && (bool) $dayShift->is_flexi_time) {
                return true;
            }

            $resolved = $this->timeLogsPayroll->resolvedLateForSession(
                $session,
                $policy,
                $dayShift?->time_in ?? $defaultShift?->time_in,
            );

            if (! $resolved['is_absent']) {
                return true;
            }
        }

        return false;
    }

    public function qualifiesForAbsentHolidayPay(
        int $employeeId,
        CarbonImmutable $holidayDate,
        ?TimekeepingPolicy $policy,
        ?ShiftCode $defaultShift,
    ): bool {
        $before = $this->adjacentWorkingDay($employeeId, $holidayDate, -1);
        $after = $this->adjacentWorkingDay($employeeId, $holidayDate, 1);

        if ($before === null || $after === null) {
            return false;
        }

        return $this->hasTimeLogOnDate($employeeId, $before, $policy, $defaultShift)
            && $this->hasTimeLogOnDate($employeeId, $after, $policy, $defaultShift);
    }

    /**
     * Unworked holiday pay at daily rate when employee has time logs before and after
     * (skipping rest days). Present-on-holiday pay comes from hours-worked rate rows.
     *
     * @return array{income_type_id: int, taxable: float, non_taxable: float, days: float}|null
     */
    public function absentHolidayIncomeForDetail(PayrollBatchDetail $detail, EmployeeSalary $salary): ?array
    {
        $detail->loadMissing([
            'employee.timekeepingSetup.policy',
            'employee.timekeepingSetup.shiftCode',
            'employee.timekeepingRestDays',
            'payrollBatch.payrollCalendar',
        ]);

        $employee = $detail->employee;
        $calendar = $detail->payrollBatch?->payrollCalendar;

        if ($employee === null || $calendar === null || $calendar->dt_from === null || $calendar->dt_to === null) {
            return null;
        }

        $periodTo = EmployeePayrollPeriod::effectiveEnd($employee, $calendar->dt_from, $calendar->dt_to);

        if ($periodTo === null) {
            return null;
        }

        $holidays = $this->holidaysForEmployeeInPeriod($employee, $calendar->dt_from, $periodTo);

        if ($holidays->isEmpty()) {
            return null;
        }

        $dailyRate = $this->employeeLoadPayroll->dailyRate($salary);

        if ($dailyRate === null || $dailyRate <= 0) {
            return null;
        }

        $holidayIncomeTypeId = IncomeType::query()
            ->where('income_type_code', 'HOLI')
            ->value('income_type_id');

        if ($holidayIncomeTypeId === null) {
            return null;
        }

        $policy = $employee->timekeepingSetup?->policy;
        $defaultShift = $employee->timekeepingSetup?->shiftCode;
        $paidDays = 0.0;

        foreach ($holidays as $holiday) {
            if ($this->hasTimeLogOnDate((int) $employee->employee_id, $holiday->date, $policy, $defaultShift)) {
                continue;
            }

            if ($this->qualifiesForAbsentHolidayPay(
                (int) $employee->employee_id,
                $holiday->date,
                $policy,
                $defaultShift,
            )) {
                $paidDays++;
            }
        }

        if ($paidDays <= 0) {
            return null;
        }

        return [
            'income_type_id' => (int) $holidayIncomeTypeId,
            'taxable' => round($dailyRate * $paidDays, 2),
            'non_taxable' => 0.0,
            'days' => $paidDays,
        ];
    }

    private function holidayOnDate(int $employeeId, CarbonImmutable $date): ?object
    {
        $employee = Employee::query()
            ->with('timekeepingSetup.holidayGroup.holidays')
            ->find($employeeId);

        if ($employee === null) {
            return null;
        }

        $holiday = $this->holidaysForEmployeeInPeriod($employee, $date, $date)->first();

        return $holiday !== null ? $holiday : null;
    }

    private function adjacentWorkingDay(int $employeeId, CarbonImmutable $holidayDate, int $direction): ?CarbonImmutable
    {
        $cursor = $holidayDate->addDays($direction > 0 ? 1 : -1);

        for ($attempt = 0; $attempt < 14; $attempt++) {
            if (! $this->isRestDayForEmployee($employeeId, $cursor)) {
                return $cursor;
            }

            $cursor = $cursor->addDays($direction > 0 ? 1 : -1);
        }

        return null;
    }

    private function matchDayTypeId(bool $isRestDay, bool $isSpecialHoliday, bool $isLegalHoliday): int
    {
        $cacheKey = ($isRestDay ? '1' : '0')
            .($isSpecialHoliday ? '1' : '0')
            .($isLegalHoliday ? '1' : '0');

        if (isset($this->dayTypeCache[$cacheKey])) {
            return $this->dayTypeCache[$cacheKey];
        }

        $dayTypeId = DayType::query()
            ->whereNull('day_id')
            ->where(fn ($query) => $this->applyDayTypeFlag($query, 'is_restday', $isRestDay))
            ->where(fn ($query) => $this->applyDayTypeFlag($query, 'is_special_holiday', $isSpecialHoliday))
            ->where(fn ($query) => $this->applyDayTypeFlag($query, 'is_legal_holiday', $isLegalHoliday))
            ->value('day_type_id');

        return $this->dayTypeCache[$cacheKey] = $dayTypeId !== null
            ? (int) $dayTypeId
            : $this->regularDayTypeId();
    }

    /**
     * @return list<int>
     */
    private function restDayIdsForEmployee(int $employeeId): array
    {
        if (isset($this->restDayIdsCache[$employeeId])) {
            return $this->restDayIdsCache[$employeeId];
        }

        $employee = Employee::query()
            ->with('timekeepingRestDays')
            ->find($employeeId);

        return $this->restDayIdsCache[$employeeId] = $employee?->timekeepingRestDays
            ->pluck('day_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all() ?? [];
    }

    private function dayIdForDate(CarbonImmutable $date): int
    {
        return (int) $date->dayOfWeek + 1;
    }

    private function regularDayTypeId(): int
    {
        if ($this->regularDayTypeId !== null) {
            return $this->regularDayTypeId;
        }

        $dayTypeId = DayType::query()
            ->where('day_type_code', 'REGU')
            ->value('day_type_id');

        return $this->regularDayTypeId = $dayTypeId !== null ? (int) $dayTypeId : 3;
    }

    private function applyDayTypeFlag(\Illuminate\Database\Eloquent\Builder $query, string $column, bool $value): void
    {
        if ($value) {
            $query->where($column, true);

            return;
        }

        $query->where(function (\Illuminate\Database\Eloquent\Builder $inner) use ($column): void {
            $inner->where($column, false)->orWhereNull($column);
        });
    }
}
