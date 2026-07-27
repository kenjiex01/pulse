<?php

namespace App\Services;

use App\Models\EmployeeSalary;
use App\Models\RawEmployeeLoadEntry;
use App\Models\RawTimekeepingInandout;
use App\Models\ShiftCode;
use App\Models\TimekeepingPolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PayrollAttendanceLeaveService
{
    public function __construct(
        private readonly EmployeeLoadPayrollService $employeeLoadPayroll,
        private readonly TimeLogsPayrollService $timeLogsPayroll,
        private readonly PayrollBreakService $breakPayroll,
    ) {}

    /**
     * @return list<array{
     *     leave_type_id: int,
     *     dt_from: CarbonInterface,
     *     dt_to: CarbonInterface,
     *     leave_hours: float,
     *     reason: string|null
     * }>
     */
    public function buildFromTimeLogSessions(
        Collection $sessions,
        ?TimekeepingPolicy $policy,
        ?string $scheduleStart,
        ?string $scheduleEnd,
        EmployeeSalary $salary,
    ): array {
        if ($policy === null) {
            return [];
        }

        $records = [];

        foreach ($sessions as $session) {
            $records = array_merge(
                $records,
                $this->recordsForSession(
                    $session['date'],
                    $session['time_in'] ?? null,
                    $session['time_out'] ?? null,
                    $scheduleStart,
                    $scheduleEnd,
                    $policy,
                    $salary,
                    fn () => $this->timeLogsPayroll->resolvedLateForSession($session, $policy, $scheduleStart),
                    fn () => $this->timeLogsPayroll->resolvedUndertimeForSession($session, $policy, $scheduleEnd),
                ),
            );
        }

        return $records;
    }

    /**
     * @return list<array{
     *     leave_type_id: int,
     *     dt_from: CarbonInterface,
     *     dt_to: CarbonInterface,
     *     leave_hours: float,
     *     reason: string|null
     * }>
     */
    public function buildFromEmployeeLoadEntries(
        Collection $entries,
        ?TimekeepingPolicy $policy,
        EmployeeSalary $salary,
    ): array {
        if ($policy === null) {
            return [];
        }

        $records = [];

        foreach ($entries as $entry) {
            if (! $entry instanceof RawEmployeeLoadEntry || $entry->session_date === null) {
                continue;
            }

            $sessionDate = CarbonImmutable::parse($entry->session_date->toDateString());
            $scheduleStart = $this->employeeLoadPayroll->parseScheduleStart($entry->class_schedule);
            $scheduleEnd = $this->employeeLoadPayroll->parseScheduleEnd($entry->class_schedule);

            $records = array_merge(
                $records,
                $this->recordsForSession(
                    $sessionDate,
                    $entry->time_in,
                    $entry->time_out,
                    $scheduleStart,
                    $scheduleEnd,
                    $policy,
                    $salary,
                    fn () => $this->employeeLoadPayroll->resolvedLateForEntry($entry, $policy),
                    fn () => $this->employeeLoadPayroll->resolvedUndertimeForEntry($entry, $policy),
                ),
            );
        }

        return $records;
    }

    /**
     * @return list<array{
     *     leave_type_id: int,
     *     dt_from: CarbonInterface,
     *     dt_to: CarbonInterface,
     *     leave_hours: float,
     *     reason: string|null
     * }>
     */
    public function buildBreakTardinessFromTimeLogPunches(
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?TimekeepingPolicy $policy,
        ?ShiftCode $shiftCode,
    ): array {
        if ($policy === null || ! filled($policy->break_tardiness_leave_type_id) || ! $this->breakPayroll->deductsBreakTardiness($policy)) {
            return [];
        }

        $scheduledMinutes = $this->breakPayroll->scheduledBreakMinutes($shiftCode);
        $records = [];

        foreach ($this->timeLogsPayroll->dayPunchesForPeriod($employeeId, $from, $to) as $date => $dayPunches) {
            $sessionDate = CarbonImmutable::parse($date);
            $actualMinutes = $this->breakPayroll->actualBreakMinutesFromPunches($dayPunches);

            if ($actualMinutes <= 0) {
                continue;
            }

            $resolved = $this->breakPayroll->resolvedBreakLateMinutes($policy, $actualMinutes, $scheduledMinutes);

            if ($resolved['billable_minutes'] <= 0) {
                continue;
            }

            $segments = $this->breakPayroll->breakSegmentsFromPunches($dayPunches);
            $segment = $segments[0] ?? null;

            $records[] = $this->makeRecord(
                (int) $policy->break_tardiness_leave_type_id,
                $segment['break_out'] ?? $sessionDate->startOfDay(),
                $segment['break_in'] ?? $sessionDate->endOfDay(),
                round($resolved['billable_minutes'] / 60, 2),
                'Break tardiness',
            );
        }

        return $records;
    }

    /**
     * @param  callable(): array{
     *     raw_minutes: int,
     *     equivalent_minutes: int|null,
     *     is_absent?: bool,
     *     billable_minutes: int
     * }  $resolveLate
     * @param  callable(): array{
     *     raw_minutes: int,
     *     equivalent_minutes: int|null,
     *     billable_minutes: int
     * }  $resolveUndertime
     * @return list<array{
     *     leave_type_id: int,
     *     dt_from: CarbonInterface,
     *     dt_to: CarbonInterface,
     *     leave_hours: float,
     *     reason: string|null
     * }>
     */
    private function recordsForSession(
        CarbonImmutable $sessionDate,
        ?string $timeIn,
        ?string $timeOut,
        ?string $scheduleStart,
        ?string $scheduleEnd,
        TimekeepingPolicy $policy,
        EmployeeSalary $salary,
        callable $resolveLate,
        callable $resolveUndertime,
    ): array {
        $records = [];
        $resolvedLate = $resolveLate();

        if (($resolvedLate['is_absent'] ?? false) && filled($policy->awol_leave_type_id)) {
            $shiftHours = $this->shiftHours($salary, $scheduleStart, $scheduleEnd);
            $records[] = $this->makeRecord(
                (int) $policy->awol_leave_type_id,
                $this->atTime($sessionDate, $scheduleStart) ?? $sessionDate->startOfDay(),
                $this->atTime($sessionDate, $scheduleEnd) ?? $sessionDate->endOfDay(),
                $shiftHours,
            );

            return $records;
        }

        if (
            filled($policy->tardiness_leave_type_id)
            && $resolvedLate['billable_minutes'] > 0
        ) {
            $records[] = $this->makeRecord(
                (int) $policy->tardiness_leave_type_id,
                $this->atTime($sessionDate, $scheduleStart) ?? $sessionDate->startOfDay(),
                $this->atTime($sessionDate, $timeIn) ?? $sessionDate->startOfDay(),
                round($resolvedLate['billable_minutes'] / 60, 2),
            );
        }

        $resolvedUndertime = $resolveUndertime();

        if (
            filled($policy->undertime_leave_type_id)
            && $resolvedUndertime['billable_minutes'] > 0
        ) {
            $records[] = $this->makeRecord(
                (int) $policy->undertime_leave_type_id,
                $this->atTime($sessionDate, $timeOut) ?? $sessionDate->startOfDay(),
                $this->atTime($sessionDate, $scheduleEnd) ?? $sessionDate->endOfDay(),
                round($resolvedUndertime['billable_minutes'] / 60, 2),
            );
        }

        return $records;
    }

    /**
     * @return array{
     *     leave_type_id: int,
     *     dt_from: CarbonInterface,
     *     dt_to: CarbonInterface,
     *     leave_hours: float,
     *     reason: string|null
     * }
     */
    private function makeRecord(
        int $leaveTypeId,
        CarbonInterface $dtFrom,
        CarbonInterface $dtTo,
        float $leaveHours,
        ?string $reason = null,
    ): array {
        return [
            'leave_type_id' => $leaveTypeId,
            'dt_from' => $dtFrom,
            'dt_to' => $dtTo->lessThan($dtFrom) ? $dtFrom : $dtTo,
            'leave_hours' => max(0, $leaveHours),
            'reason' => $reason,
        ];
    }

    private function atTime(CarbonImmutable $date, ?string $time): ?CarbonImmutable
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        try {
            return $date->setTimeFromTimeString($time);
        } catch (\Throwable) {
            return null;
        }
    }

    private function shiftHours(EmployeeSalary $salary, ?string $scheduleStart, ?string $scheduleEnd): float
    {
        if ($scheduleStart !== null && $scheduleEnd !== null) {
            try {
                $start = CarbonImmutable::parse('2000-01-01 '.$scheduleStart);
                $end = CarbonImmutable::parse('2000-01-01 '.$scheduleEnd);

                if ($end->lessThanOrEqualTo($start)) {
                    $end = $end->addDay();
                }

                return round($start->diffInMinutes($end) / 60, 2);
            } catch (\Throwable) {
                // Fall through to salary default.
            }
        }

        $hoursPerDay = (float) ($salary->hours_per_day ?? 0);

        return $hoursPerDay > 0 ? round($hoursPerDay, 2) : 8.0;
    }
}
