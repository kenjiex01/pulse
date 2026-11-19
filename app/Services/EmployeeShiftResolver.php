<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeShiftOverride;
use App\Models\LuDay;
use App\Models\ShiftCode;
use App\Models\TimekeepingEmployeeWeeklyShift;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EmployeeShiftResolver
{
    /**
     * @var array<int, array<string, ShiftCode|null>>
     */
    private array $overrideCache = [];

    /**
     * @var array<int, ShiftCode|null>
     */
    private array $defaultCache = [];

    /**
     * @var array<int, array<int, ShiftCode|null>>
     */
    private array $weeklyCache = [];

    /**
     * @var array<int, true>
     */
    private array $overrideRangeLoaded = [];

    public function forDate(Employee|int $employee, CarbonInterface $date, ?ShiftCode $defaultShift = null): ?ShiftCode
    {
        $employeeId = $employee instanceof Employee ? (int) $employee->employee_id : (int) $employee;
        $dateKey = $date->toDateString();

        if (! array_key_exists($employeeId, $this->overrideCache)) {
            $this->overrideCache[$employeeId] = [];
        }

        if (array_key_exists($dateKey, $this->overrideCache[$employeeId])) {
            $overrideShift = $this->overrideCache[$employeeId][$dateKey];
            if ($overrideShift !== null) {
                return $overrideShift;
            }

            return $defaultShift ?? $this->defaultShiftFor($employee);
        }

        $override = EmployeeShiftOverride::query()
            ->with(['shiftCode.breaks'])
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $dateKey)
            ->first();

        $overrideShift = $override?->shiftCode;
        $this->overrideCache[$employeeId][$dateKey] = $overrideShift;

        if ($overrideShift !== null) {
            return $overrideShift;
        }

        $weeklyShift = $this->weeklyShiftFor($employeeId, LuDay::idFromDate($date));
        if ($weeklyShift !== null) {
            return $weeklyShift;
        }

        return $defaultShift ?? $this->defaultShiftFor($employee);
    }

    /**
     * @param  iterable<Employee>  $employees
     */
    public function preloadEmployees(iterable $employees, CarbonInterface $from, CarbonInterface $to): void
    {
        $ids = [];

        foreach ($employees as $employee) {
            if (! $employee instanceof Employee) {
                continue;
            }

            $employeeId = (int) $employee->employee_id;
            $ids[] = $employeeId;
            $employee->loadMissing('timekeepingSetup.shiftCode');
            $this->defaultCache[$employeeId] = $employee->timekeepingSetup?->shiftCode;
            $this->overrideRangeLoaded[$employeeId] = true;
            $this->overrideCache[$employeeId] = $this->overrideCache[$employeeId] ?? [];
            $this->weeklyCache[$employeeId] = [];
        }

        if ($ids === []) {
            return;
        }

        $rangeStart = $from->copy()->startOfDay();
        $rangeEnd = $to->copy()->startOfDay();

        foreach ($ids as $employeeId) {
            $cursor = $rangeStart->copy();

            while ($cursor->lte($rangeEnd)) {
                $key = $cursor->toDateString();

                if (! array_key_exists($key, $this->overrideCache[$employeeId])) {
                    $this->overrideCache[$employeeId][$key] = null;
                }

                $cursor = $cursor->addDay();
            }
        }

        $weeklyRows = TimekeepingEmployeeWeeklyShift::query()
            ->with(['shiftCode.breaks'])
            ->whereIn('employee_id', $ids)
            ->get();

        foreach ($weeklyRows as $row) {
            $this->weeklyCache[(int) $row->employee_id][(int) $row->day_id] = $row->shiftCode;
        }

        $overrides = EmployeeShiftOverride::query()
            ->with(['shiftCode.breaks'])
            ->whereIn('employee_id', $ids)
            ->whereDate('work_date', '>=', $rangeStart->toDateString())
            ->whereDate('work_date', '<=', $rangeEnd->toDateString())
            ->get();

        foreach ($overrides as $row) {
            $key = $row->work_date?->toDateString();

            if ($key === null) {
                continue;
            }

            $this->overrideCache[(int) $row->employee_id][$key] = $row->shiftCode;
        }
    }

    public function loadOverridesForRange(int $employeeId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        if (! empty($this->overrideRangeLoaded[$employeeId])) {
            $this->ensureWeeklyShiftsLoaded($employeeId);

            return $this->overrideMapFromCache($employeeId, $from, $to);
        }

        $this->ensureWeeklyShiftsLoaded($employeeId);

        $rows = EmployeeShiftOverride::query()
            ->with(['shiftCode.breaks'])
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->get();

        if (! array_key_exists($employeeId, $this->overrideCache)) {
            $this->overrideCache[$employeeId] = [];
        }

        // Seed all dates in range as "no override" so forDate() does not N+1.
        // Reassign cursor so this works with CarbonImmutable as well as Carbon.
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            if (! array_key_exists($key, $this->overrideCache[$employeeId])) {
                $this->overrideCache[$employeeId][$key] = null;
            }
            $cursor = $cursor->addDay();
        }

        $map = collect();
        foreach ($rows as $row) {
            $key = $row->work_date?->toDateString();
            if ($key === null || $row->shiftCode === null) {
                continue;
            }
            $map->put($key, $row->shiftCode);
            $this->overrideCache[$employeeId][$key] = $row->shiftCode;
        }

        return $map;
    }

    public function clearCache(): void
    {
        $this->overrideCache = [];
        $this->defaultCache = [];
        $this->weeklyCache = [];
        $this->overrideRangeLoaded = [];
    }

    private function overrideMapFromCache(int $employeeId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $map = collect();
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $shift = $this->overrideCache[$employeeId][$key] ?? null;

            if ($shift !== null) {
                $map->put($key, $shift);
            }

            $cursor = $cursor->addDay();
        }

        return $map;
    }

    private function weeklyShiftFor(int $employeeId, int $dayId): ?ShiftCode
    {
        $this->ensureWeeklyShiftsLoaded($employeeId);

        return $this->weeklyCache[$employeeId][$dayId] ?? null;
    }

    private function ensureWeeklyShiftsLoaded(int $employeeId): void
    {
        if (array_key_exists($employeeId, $this->weeklyCache)) {
            return;
        }

        $this->weeklyCache[$employeeId] = [];
        $rows = TimekeepingEmployeeWeeklyShift::query()
            ->with(['shiftCode.breaks'])
            ->where('employee_id', $employeeId)
            ->get();

        foreach ($rows as $row) {
            $this->weeklyCache[$employeeId][(int) $row->day_id] = $row->shiftCode;
        }
    }

    private function defaultShiftFor(Employee|int $employee): ?ShiftCode
    {
        if ($employee instanceof Employee) {
            $employeeId = (int) $employee->employee_id;
            if (array_key_exists($employeeId, $this->defaultCache)) {
                return $this->defaultCache[$employeeId];
            }

            $employee->loadMissing('timekeepingSetup.shiftCode.breaks');
            $shift = $employee->timekeepingSetup?->shiftCode;
            $this->defaultCache[$employeeId] = $shift;

            return $shift;
        }

        $employeeId = (int) $employee;
        if (array_key_exists($employeeId, $this->defaultCache)) {
            return $this->defaultCache[$employeeId];
        }

        $model = Employee::query()
            ->with('timekeepingSetup.shiftCode.breaks')
            ->find($employeeId);

        $shift = $model?->timekeepingSetup?->shiftCode;
        $this->defaultCache[$employeeId] = $shift;

        return $shift;
    }
}
