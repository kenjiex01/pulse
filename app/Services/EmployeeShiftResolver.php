<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeShiftOverride;
use App\Models\ShiftCode;
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

        return $defaultShift ?? $this->defaultShiftFor($employee);
    }

    /**
     * Prefetch overrides for an employee across a date range to avoid N+1.
     *
     * @return Collection<string, ShiftCode> keyed by Y-m-d (only days with overrides)
     */
    public function loadOverridesForRange(int $employeeId, CarbonInterface $from, CarbonInterface $to): Collection
    {
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
