<?php

namespace App\Services;

use App\Models\BasicComputation;
use App\Models\DayType;
use App\Models\EmployeeSalary;
use App\Models\PayrollBatchDetail;
use App\Models\RawEmployeeLoadEntry;
use App\Models\ShiftCode;
use App\Models\TimekeepingPolicy;
use App\Models\TimeType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PayrollAttendanceHoursWorkedService
{
    private ?int $regularDayTypeId = null;

    public function __construct(
        private readonly TimeLogsPayrollService $timeLogsPayroll,
        private readonly EmployeeLoadPayrollService $employeeLoadPayroll,
        private readonly FacultyLoadPayrollService $facultyLoadPayroll,
        private readonly PayrollOvertimeService $overtimePayroll,
        private readonly PayrollBreakService $breakPayroll,
        private readonly FlexiShiftPayrollService $flexiShiftPayroll,
    ) {}

    /**
     * Hours derived from time logs or employee load for the batch pay period,
     * grouped by day type and time type (BP, OT, SOT, NDIF, etc.).
     *
     * @return Collection<int, object{
     *     day_type_id: int,
     *     time_type_id: int,
     *     hours: float,
     *     time_type_code: string|null
     * }>
     */
    public function rowsForDetail(PayrollBatchDetail $detail, EmployeeSalary $salary): Collection
    {
        $detail->loadMissing([
            'payrollBatch.payrollCalendar',
            'employee.timekeepingSetup.policy',
            'employee.timekeepingSetup.shiftCode.breaks',
            'employee.employmentInformations',
        ]);

        $employee = $detail->employee;
        $isFaculty = $employee?->isFaculty() ?? false;

        // Faculty always derive hours from teaching loads (even when salary Basic Computation is Leaves).
        // Staff/non-faculty still require Time-In/Time-Out basic computation.
        if (! $isFaculty && (int) $salary->basic_computation_id !== BasicComputation::TIME_IN_OUT) {
            return collect();
        }

        $batch = $detail->payrollBatch;
        $calendar = $batch?->payrollCalendar;

        if ($calendar === null) {
            return collect();
        }

        $policy = $employee?->timekeepingSetup?->policy;
        $shiftCode = $employee?->timekeepingSetup?->shiftCode;
        $scheduleStart = $shiftCode?->time_in;
        $scheduleEnd = $shiftCode?->time_out;

        $totals = [];

        if ($employee !== null && $this->facultyLoadPayroll->shouldUseFacultyLoadPath(
            $employee,
            $salary,
            $calendar->dt_from,
            $calendar->dt_to,
        )) {
            $facultyHours = $this->facultyLoadPayroll->hourTotalsForPeriod(
                $employee,
                $calendar->dt_from,
                $calendar->dt_to,
                $policy,
                $shiftCode,
            );

            if (($facultyHours['basic_hours'] ?? 0) > 0) {
                $this->addHours($totals, $this->regularDayTypeId(), 1, (float) $facultyHours['basic_hours']);
            }
        } elseif ($this->timeLogsPayroll->hasPunchesInPeriod(
            (int) $detail->employee_id,
            $calendar->dt_from,
            $calendar->dt_to,
        )) {
            $sessions = $this->timeLogsPayroll->daySessionsForPeriod(
                (int) $detail->employee_id,
                $calendar->dt_from,
                $calendar->dt_to,
            );

            $dayPunches = $this->timeLogsPayroll->dayPunchesForPeriod(
                (int) $detail->employee_id,
                $calendar->dt_from,
                $calendar->dt_to,
            );

            foreach ($sessions as $session) {
                $dateKey = $session['date']->toDateString();
                $punches = $dayPunches->get($dateKey, collect());

                $this->accumulateSessionHours(
                    $totals,
                    $session['date'],
                    $session['time_in'],
                    $session['time_out'],
                    $scheduleStart,
                    $scheduleEnd,
                    $policy,
                    $shiftCode,
                    $this->breakLateMinutesForDay($punches, $policy, $shiftCode),
                    $punches,
                );
            }
        } else {
            $entries = $this->employeeLoadPayroll->entriesForEmployeeInPeriod(
                (int) $detail->employee_id,
                $employee?->employee_number,
                $calendar->dt_from,
                $calendar->dt_to,
            );

            foreach ($entries as $entry) {
                if (! $entry instanceof RawEmployeeLoadEntry || $entry->session_date === null) {
                    continue;
                }

                $sessionDate = CarbonImmutable::parse($entry->session_date->toDateString());

                $this->accumulateSessionHours(
                    $totals,
                    $sessionDate,
                    $entry->time_in,
                    $entry->time_out,
                    $this->employeeLoadPayroll->parseScheduleStart($entry->class_schedule) ?? $scheduleStart,
                    $this->employeeLoadPayroll->parseScheduleEnd($entry->class_schedule) ?? $scheduleEnd,
                    $policy,
                    $shiftCode,
                    0,
                    collect(),
                );
            }
        }

        return $this->totalsToRows($totals);
    }

    /**
     * @param  array<string, float>  $totals
     */
    private function accumulateSessionHours(
        array &$totals,
        CarbonImmutable $sessionDate,
        ?string $timeIn,
        ?string $timeOut,
        ?string $scheduleStart,
        ?string $scheduleEnd,
        ?TimekeepingPolicy $policy,
        ?ShiftCode $shiftCode,
        int $breakLateMinutes,
        Collection $dayPunches,
    ): void {
        if ($timeIn === null || $timeIn === '') {
            return;
        }

        if ($this->flexiShiftPayroll->isFlexiShift($shiftCode)) {
            $this->accumulateFlexiSessionHours($totals, $sessionDate, $timeIn, $timeOut, $policy, $shiftCode, $dayPunches);

            return;
        }

        $session = [
            'date' => $sessionDate,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
        ];

        $lateResolved = $this->timeLogsPayroll->resolvedLateForSession($session, $policy, $scheduleStart);

        if ($lateResolved['is_absent']) {
            return;
        }

        $dayTypeId = $this->regularDayTypeId();
        $scheduledHours = $this->scheduledHours($sessionDate, $scheduleStart, $scheduleEnd, $shiftCode);
        $deductionHours = (
            $lateResolved['billable_minutes']
            + $this->timeLogsPayroll->resolvedUndertimeForSession($session, $policy, $scheduleEnd)['billable_minutes']
            + $breakLateMinutes
        ) / 60;

        $basicHours = max(0.0, round($scheduledHours - $deductionHours, 4));

        if ($basicHours > 0) {
            $this->addHours($totals, $dayTypeId, 1, $basicHours);
        }

        $overtimeBreakdown = $this->overtimePayroll->billableMinutesBreakdownForSession(
            $sessionDate,
            $timeIn,
            $timeOut,
            $scheduleStart,
            $scheduleEnd,
            $policy,
        );

        if ($overtimeBreakdown['regular_minutes'] > 0) {
            $this->addHours($totals, $dayTypeId, 2, $overtimeBreakdown['regular_minutes'] / 60);
        }

        if ($overtimeBreakdown['special_minutes'] > 0) {
            $this->addHours($totals, $dayTypeId, 3, $overtimeBreakdown['special_minutes'] / 60);
        }

        $nightDiffHours = $this->nightDiffHoursForSession($sessionDate, $timeIn, $timeOut, $policy);

        if ($nightDiffHours > 0) {
            $this->addHours($totals, $dayTypeId, 4, $nightDiffHours);
        }
    }

    /**
     * @param  array<string, float>  $totals
     * @param  Collection<int, \App\Models\RawTimekeepingInandout>  $dayPunches
     */
    private function accumulateFlexiSessionHours(
        array &$totals,
        CarbonImmutable $sessionDate,
        ?string $timeIn,
        ?string $timeOut,
        ?TimekeepingPolicy $policy,
        ?ShiftCode $shiftCode,
        Collection $dayPunches,
    ): void {
        $breakdown = $this->flexiShiftPayroll->dailyHoursBreakdown(
            $sessionDate,
            $timeIn,
            $timeOut,
            $shiftCode,
            $dayPunches,
        );

        if ($breakdown['actual_hours'] <= 0) {
            return;
        }

        $dayTypeId = $this->regularDayTypeId();

        if ($breakdown['basic_hours'] > 0) {
            $this->addHours($totals, $dayTypeId, 1, $breakdown['basic_hours']);
        }

        if ($breakdown['overtime_hours'] > 0) {
            $this->addHours($totals, $dayTypeId, 2, $breakdown['overtime_hours']);
        }

        $nightDiffHours = $this->nightDiffHoursForSession($sessionDate, $timeIn, $timeOut, $policy);

        if ($nightDiffHours > 0) {
            $this->addHours($totals, $dayTypeId, 4, $nightDiffHours);
        }
    }

    /**
     * @param  Collection<int, \App\Models\RawTimekeepingInandout>  $dayPunches
     */
    private function breakLateMinutesForDay(
        Collection $dayPunches,
        ?TimekeepingPolicy $policy,
        ?ShiftCode $shiftCode,
    ): int {
        if ($policy === null || ! $this->breakPayroll->deductsBreakTardiness($policy)) {
            return 0;
        }

        $actualMinutes = $this->breakPayroll->actualBreakMinutesFromPunches($dayPunches);

        if ($actualMinutes <= 0) {
            return 0;
        }

        return $this->breakPayroll->resolvedBreakLateMinutes(
            $policy,
            $actualMinutes,
            $this->breakPayroll->scheduledBreakMinutes($shiftCode),
        )['billable_minutes'];
    }

    private function scheduledHours(
        CarbonImmutable $sessionDate,
        ?string $scheduleStart,
        ?string $scheduleEnd,
        ?ShiftCode $shiftCode,
    ): float {
        if ($scheduleStart === null || $scheduleStart === '' || $scheduleEnd === null || $scheduleEnd === '') {
            return 0.0;
        }

        try {
            $startAt = $sessionDate->setTimeFromTimeString($scheduleStart);
            $endAt = $sessionDate->setTimeFromTimeString($scheduleEnd);

            if ($endAt->lessThanOrEqualTo($startAt)) {
                $endAt = $endAt->addDay();
            }

            $scheduledMinutes = (int) $startAt->diffInMinutes($endAt);
            $scheduledMinutes -= $this->breakPayroll->scheduledBreakMinutes($shiftCode);

            return max(0.0, round($scheduledMinutes / 60, 4));
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function nightDiffHoursForSession(
        CarbonImmutable $sessionDate,
        ?string $timeIn,
        ?string $timeOut,
        ?TimekeepingPolicy $policy,
    ): float {
        if (
            $policy === null
            || $timeIn === null
            || $timeIn === ''
            || $timeOut === null
            || $timeOut === ''
        ) {
            return 0.0;
        }

        $nightStart = trim((string) ($policy->night_diff_start ?? ''));
        $nightEnd = trim((string) ($policy->night_diff_end ?? ''));

        if ($nightStart === '' || $nightEnd === '') {
            return 0.0;
        }

        try {
            $workStart = $sessionDate->setTimeFromTimeString($timeIn);
            $workEnd = $sessionDate->setTimeFromTimeString($timeOut);

            if ($workEnd->lessThanOrEqualTo($workStart)) {
                $workEnd = $workEnd->addDay();
            }

            $overlapMinutes = $this->overlapMinutesWithNightWindow(
                $workStart,
                $workEnd,
                $nightStart,
                $nightEnd,
            );

            return $overlapMinutes > 0 ? round($overlapMinutes / 60, 4) : 0.0;
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function overlapMinutesWithNightWindow(
        CarbonImmutable $workStart,
        CarbonImmutable $workEnd,
        string $nightStart,
        string $nightEnd,
    ): int {
        $total = 0;
        $cursor = $workStart->startOfDay()->subDay();

        for ($dayOffset = 0; $dayOffset <= 2; $dayOffset++) {
            $base = $cursor->addDays($dayOffset);

            try {
                $nightStartAt = $base->setTimeFromTimeString($nightStart);
                $nightEndAt = $base->setTimeFromTimeString($nightEnd);

                if ($nightEndAt->lessThanOrEqualTo($nightStartAt)) {
                    $nightEndAt = $nightEndAt->addDay();
                }

                $overlapStart = $workStart->greaterThan($nightStartAt) ? $workStart : $nightStartAt;
                $overlapEnd = $workEnd->lessThan($nightEndAt) ? $workEnd : $nightEndAt;

                if ($overlapEnd->greaterThan($overlapStart)) {
                    $total += (int) $overlapStart->diffInMinutes($overlapEnd);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $total;
    }

    /**
     * @param  array<string, float>  $totals
     */
    private function addHours(array &$totals, int $dayTypeId, int $timeTypeId, float $hours): void
    {
        if ($hours <= 0) {
            return;
        }

        $key = $dayTypeId.'-'.$timeTypeId;
        $totals[$key] = ($totals[$key] ?? 0.0) + $hours;
    }

    /**
     * @param  array<string, float>  $totals
     * @return Collection<int, object{
     *     day_type_id: int,
     *     time_type_id: int,
     *     hours: float,
     *     time_type_code: string|null
     * }>
     */
    private function totalsToRows(array $totals): Collection
    {
        if ($totals === []) {
            return collect();
        }

        $timeTypeCodes = TimeType::query()
            ->whereIn('time_type_id', collect($totals)->keys()->map(function (string $key) {
                [, $timeTypeId] = explode('-', $key);

                return (int) $timeTypeId;
            })->unique()->all())
            ->pluck('time_type_code', 'time_type_id');

        $rows = [];

        foreach ($totals as $key => $hours) {
            [$dayTypeId, $timeTypeId] = array_map('intval', explode('-', $key, 2));

            $rows[] = (object) [
                'day_type_id' => $dayTypeId,
                'time_type_id' => $timeTypeId,
                'hours' => round($hours, 4),
                'time_type_code' => $timeTypeCodes[$timeTypeId] ?? null,
            ];
        }

        return collect($rows);
    }

    private function regularDayTypeId(): int
    {
        if ($this->regularDayTypeId !== null) {
            return $this->regularDayTypeId;
        }

        $dayTypeId = DayType::query()
            ->where('day_type_code', 'REGU')
            ->value('day_type_id');

        $this->regularDayTypeId = $dayTypeId !== null ? (int) $dayTypeId : 3;

        return $this->regularDayTypeId;
    }
}
