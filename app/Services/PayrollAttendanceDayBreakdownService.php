<?php

namespace App\Services;

use App\Models\PayrollAttendanceDay;
use App\Models\PayrollBatchDetail;
use App\Models\ShiftCode;
use App\Models\TimekeepingPolicy;
use App\Support\EmployeePayrollPeriod;
use Carbon\CarbonInterface;

/**
 * Per-day late / undertime / overtime breakdown for payroll batch employee detail modals.
 */
class PayrollAttendanceDayBreakdownService
{
    public function __construct(
        private readonly TimeLogsPayrollService $timeLogsPayroll,
        private readonly EmployeeShiftResolver $shiftResolver,
        private readonly PayrollBreakService $breakPayroll,
        private readonly EmployeeOvertimeApprovalService $overtimeApprovals,
    ) {}

    /**
     * @return array{
     *     LTDE: list<array<string, mixed>>,
     *     UTDE: list<array<string, mixed>>,
     *     OVRT: list<array<string, mixed>>
     * }
     */
    public function forDetail(PayrollBatchDetail $detail): array
    {
        $breakdown = $this->attendanceBreakdown($detail);

        foreach ($this->deductionLineDays($detail) as $code => $rows) {
            if ($rows === []) {
                continue;
            }

            $breakdown[$code] = collect($breakdown[$code])
                ->map(fn (array $row) => $row + ['source' => 'Biometric'])
                ->concat($rows)
                ->sortBy('work_date')
                ->values()
                ->all();
        }

        return $breakdown;
    }

    /**
     * Manually added and uploaded LTDE/UTDE lines that carry their own work date and time.
     *
     * @return array{LTDE: list<array<string, mixed>>, UTDE: list<array<string, mixed>>}
     */
    private function deductionLineDays(PayrollBatchDetail $detail): array
    {
        $rows = ['LTDE' => [], 'UTDE' => []];

        $lines = $detail->deductions()
            ->with('deductionType')
            ->whereNotNull('dt_reference')
            ->whereHas('deductionType', fn ($query) => $query->whereIn('deduction_type_code', array_keys($rows)))
            ->get();

        if ($lines->isEmpty()) {
            return $rows;
        }

        $employeeId = (int) $detail->employee_id;
        $defaultShift = $detail->employee?->timekeepingSetup?->shiftCode;
        $dates = $lines->map(fn ($line) => $line->dt_reference->copy()->startOfDay());
        $this->shiftResolver->loadOverridesForRange($employeeId, $dates->min(), $dates->max());

        foreach ($lines as $line) {
            $code = $line->deductionType->deduction_type_code;
            $date = $line->dt_reference->copy()->startOfDay();
            $shift = $this->shiftResolver->forDate($employeeId, $date, $defaultShift);

            $rows[$code][] = [
                'work_date' => $date->toDateString(),
                'shift_code' => $shift?->shift_code,
                'schedule_start' => $this->formatClock($shift?->time_in),
                'schedule_end' => $this->formatClock($shift?->time_out),
                'time_in' => $code === 'LTDE' ? $this->formatClock($line->time_in) : null,
                'time_out' => $code === 'UTDE' ? $this->formatClock($line->time_out) : null,
                'minutes' => (int) round((float) $line->hours * 60),
                'source' => $line->is_manual ? 'Manual' : 'Upload',
            ];
        }

        return $rows;
    }

    /**
     * @return array{
     *     LTDE: list<array<string, mixed>>,
     *     UTDE: list<array<string, mixed>>,
     *     OVRT: list<array<string, mixed>>
     * }
     */
    private function attendanceBreakdown(PayrollBatchDetail $detail): array
    {
        $detail->loadMissing([
            'payrollBatch.payrollCalendar',
            'employee.timekeepingSetup.policy',
            'employee.timekeepingSetup.shiftCode',
            'attendanceDays.shiftCode',
        ]);

        $savedDays = $detail->attendanceDays;

        if ($savedDays->isNotEmpty()) {
            return $this->fromSavedDays($savedDays);
        }

        $calendar = $detail->payrollBatch?->payrollCalendar;
        $from = $calendar?->dt_from;
        $to = $calendar?->dt_to;

        if ($from === null || $to === null || $detail->employee === null) {
            return ['LTDE' => [], 'UTDE' => [], 'OVRT' => []];
        }

        $to = EmployeePayrollPeriod::effectiveEnd($detail->employee, $from, $to);

        if ($to === null) {
            return ['LTDE' => [], 'UTDE' => [], 'OVRT' => []];
        }

        $employeeId = (int) $detail->employee_id;
        $employee = $detail->employee;
        $policy = $employee?->timekeepingSetup?->policy;
        $defaultShift = $employee?->timekeepingSetup?->shiftCode;

        if (! $this->timeLogsPayroll->hasPunchesInPeriod($employeeId, $from, $to)) {
            return [
                'LTDE' => [],
                'UTDE' => [],
                'OVRT' => $this->overtimeApprovals->dayBreakdownForPeriod($employeeId, $from, $to, $defaultShift),
            ];
        }

        $otRows = $this->overtimeApprovals->dayBreakdownForPeriod($employeeId, $from, $to, $defaultShift);
        $otMinutesByDate = [];
        foreach ($otRows as $row) {
            $otMinutesByDate[(string) $row['work_date']] = (int) ($row['minutes'] ?? 0);
        }

        $otRows = $this->applyOtOffsetsToRows(
            $otRows,
            $employeeId,
            $policy,
            $defaultShift,
            $otMinutesByDate,
        );

        return [
            'LTDE' => $this->lateDays($employeeId, $from, $to, $policy, $defaultShift),
            'UTDE' => $this->undertimeDays($employeeId, $from, $to, $policy, $defaultShift),
            'OVRT' => $otRows,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PayrollAttendanceDay>  $savedDays
     * @return array{
     *     LTDE: list<array<string, mixed>>,
     *     UTDE: list<array<string, mixed>>,
     *     OVRT: list<array<string, mixed>>
     * }
     */
    private function fromSavedDays($savedDays): array
    {
        $late = [];
        $undertime = [];
        $overtime = [];

        foreach ($savedDays->sortBy('work_date') as $day) {
            $workDate = $day->work_date?->toDateString();
            if ($workDate === null) {
                continue;
            }

            $shift = $day->shiftCode;
            $base = [
                'work_date' => $workDate,
                'shift_code' => $shift?->shift_code,
                'schedule_start' => $this->formatClock($shift?->time_in),
                'schedule_end' => $this->formatClock($shift?->time_out),
                'time_in' => $this->formatClock($day->time_in),
                'time_out' => $this->formatClock($day->time_out),
            ];

            $lateHours = (float) ($day->late ?? 0);
            $breakLateHours = (float) ($day->break_late ?? 0);
            $lateMinutes = (int) round(($lateHours + $breakLateHours) * 60);
            if ($lateMinutes > 0) {
                $late[] = $base + [
                    'minutes' => $lateMinutes,
                    'clock_late_minutes' => (int) round($lateHours * 60),
                    'break_late_minutes' => (int) round($breakLateHours * 60),
                ];
            }

            $undertimeMinutes = (int) round(((float) ($day->undertime ?? 0)) * 60);
            if ($undertimeMinutes > 0) {
                $undertime[] = $base + ['minutes' => $undertimeMinutes];
            }

            $otMinutes = (int) round(((float) ($day->ot ?? 0)) * 60);
            if ($otMinutes > 0) {
                $overtime[] = $base + [
                    'minutes' => $otMinutes,
                    'ot_start' => null,
                    'ot_end' => null,
                ];
            }
        }

        return [
            'LTDE' => $late,
            'UTDE' => $undertime,
            'OVRT' => $overtime,
        ];
    }

    /**
     * Per-day OT minutes after the same tardiness offset used by processed payroll batches.
     *
     * @return array<string, int> Y-m-d => minutes
     */
    public function otMinutesByDate(
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?TimekeepingPolicy $policy,
        ?ShiftCode $defaultShift,
    ): array {
        $otRows = $this->overtimeApprovals->dayBreakdownForPeriod($employeeId, $from, $to, $defaultShift);
        $rawByDate = [];

        foreach ($otRows as $row) {
            $rawByDate[(string) ($row['work_date'] ?? '')] = (int) ($row['minutes'] ?? 0);
        }

        if ($this->timeLogsPayroll->hasPunchesInPeriod($employeeId, $from, $to)) {
            $otRows = $this->applyOtOffsetsToRows(
                $otRows,
                $employeeId,
                $policy,
                $defaultShift,
                $rawByDate,
            );
        }

        $byDate = [];

        foreach ($otRows as $row) {
            $dateKey = (string) ($row['work_date'] ?? '');

            if ($dateKey === '') {
                continue;
            }

            $byDate[$dateKey] = (int) ($row['minutes'] ?? 0);
        }

        return $byDate;
    }

    /**
     * Reduce OT day minutes by absent-tardiness offset when policy is enabled.
     *
     * @param  list<array<string, mixed>>  $otRows
     * @param  array<string, int>  $otMinutesByDate
     * @return list<array<string, mixed>>
     */
    private function applyOtOffsetsToRows(
        array $otRows,
        int $employeeId,
        ?TimekeepingPolicy $policy,
        ?ShiftCode $defaultShift,
        array $otMinutesByDate,
    ): array {
        if ($policy === null || ! (bool) $policy->is_offset_absent_tardiness_with_ot || $otRows === []) {
            return $otRows;
        }

        $adjusted = [];

        foreach ($otRows as $row) {
            $dateKey = (string) ($row['work_date'] ?? '');
            $session = [
                'date' => \Carbon\CarbonImmutable::parse($dateKey),
                'time_in' => isset($row['time_in']) ? $row['time_in'].':00' : null,
                'time_out' => isset($row['time_out']) ? $row['time_out'].':00' : null,
            ];

            // Prefer H:i from breakdown; lateRaw accepts H:i:s via setTimeFromTimeString.
            if (($row['time_in'] ?? null) && strlen((string) $row['time_in']) === 5) {
                $session['time_in'] = $row['time_in'].':00';
            }
            if (($row['time_out'] ?? null) && strlen((string) $row['time_out']) === 5) {
                $session['time_out'] = $row['time_out'].':00';
            }

            $schedule = $this->timeLogsPayroll->scheduleForSession($employeeId, $session, $defaultShift);
            $resolved = $this->timeLogsPayroll->resolvedLateForSession(
                $session,
                $policy,
                $schedule['start'],
                (int) ($otMinutesByDate[$dateKey] ?? 0),
                $schedule['shift'],
            );

            $offset = (int) ($resolved['ot_offset_minutes'] ?? 0);
            $minutes = max(0, (int) ($row['minutes'] ?? 0) - $offset);

            if ($minutes <= 0) {
                continue;
            }

            $row['minutes'] = $minutes;
            $adjusted[] = $row;
        }

        return $adjusted;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lateDays(
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?TimekeepingPolicy $policy,
        ?ShiftCode $defaultShift,
    ): array {
        $this->shiftResolver->loadOverridesForRange($employeeId, $from, $to);
        $sessions = $this->timeLogsPayroll->daySessionsForPeriod($employeeId, $from, $to);
        $dayPunches = $this->timeLogsPayroll->dayPunchesForPeriod($employeeId, $from, $to);
        $includeBreakLate = $policy !== null && $this->breakPayroll->deductsBreakTardiness($policy);
        $otMinutesByDate = $this->overtimeApprovals->billableMinutesByDate($employeeId, $from, $to, $defaultShift);

        $rows = [];

        foreach ($sessions as $session) {
            if (($session['time_in'] ?? null) === null || $session['time_in'] === '') {
                continue;
            }

            $schedule = $this->timeLogsPayroll->scheduleForSession($employeeId, $session, $defaultShift);
            $shift = $schedule['shift'];

            if ($shift !== null && (bool) $shift->is_flexi_time) {
                continue;
            }

            $dateKey = $session['date']->toDateString();
            $otForDay = (int) ($otMinutesByDate[$dateKey] ?? 0);
            $resolvedLate = $this->timeLogsPayroll->resolvedLateForSession(
                $session,
                $policy,
                $schedule['start'],
                $otForDay,
                $shift,
            );

            if ($resolvedLate['is_absent'] || ($resolvedLate['is_half_day'] ?? false)) {
                continue;
            }

            // Absent+OT offset waived late for this day — nothing to show under Late.
            if ((int) ($resolvedLate['ot_offset_minutes'] ?? 0) > 0) {
                continue;
            }

            $clockLate = (int) $resolvedLate['billable_minutes'];
            $breakLate = 0;

            if ($includeBreakLate) {
                $punches = $dayPunches->get($dateKey);

                if ($punches !== null && $this->breakPayroll->breakSegmentsFromPunches($punches) !== []) {
                    $breakLate = $this->breakPayroll->resolvedBreakLateMinutesForDay(
                        $policy,
                        $punches,
                        $shift,
                        $session['date'],
                    )['billable_minutes'];
                }
            }

            $minutes = $clockLate + $breakLate;

            if ($minutes <= 0) {
                continue;
            }

            $rows[] = $this->baseDayRow($session, $schedule, $minutes) + [
                'clock_late_minutes' => $clockLate,
                'break_late_minutes' => $breakLate,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function undertimeDays(
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?TimekeepingPolicy $policy,
        ?ShiftCode $defaultShift,
    ): array {
        $this->shiftResolver->loadOverridesForRange($employeeId, $from, $to);
        $sessions = $this->timeLogsPayroll->daySessionsForPeriod($employeeId, $from, $to);
        $otMinutesByDate = $this->overtimeApprovals->billableMinutesByDate($employeeId, $from, $to, $defaultShift);
        $rows = [];

        foreach ($sessions as $session) {
            if (($session['time_in'] ?? null) === null || $session['time_in'] === '') {
                continue;
            }

            $schedule = $this->timeLogsPayroll->scheduleForSession($employeeId, $session, $defaultShift);
            $shift = $schedule['shift'];

            if ($shift !== null && (bool) $shift->is_flexi_time) {
                continue;
            }

            $dateKey = $session['date']->toDateString();
            $resolvedLate = $this->timeLogsPayroll->resolvedLateForSession(
                $session,
                $policy,
                $schedule['start'],
                (int) ($otMinutesByDate[$dateKey] ?? 0),
                $shift,
            );

            if ($resolvedLate['is_absent']) {
                continue;
            }

            $minutes = (int) $this->timeLogsPayroll
                ->resolvedUndertimeForSession($session, $policy, $schedule['end'], $schedule['start'])['billable_minutes'];

            if ($minutes <= 0) {
                continue;
            }

            $rows[] = $this->baseDayRow($session, $schedule, $minutes);
        }

        return $rows;
    }

    /**
     * @param  array{date: \Carbon\CarbonImmutable, time_in: string|null, time_out: string|null}  $session
     * @param  array{shift: ?ShiftCode, start: ?string, end: ?string}  $schedule
     * @return array{
     *     work_date: string,
     *     shift_code: string|null,
     *     schedule_start: string|null,
     *     schedule_end: string|null,
     *     time_in: string|null,
     *     time_out: string|null,
     *     minutes: int
     * }
     */
    private function baseDayRow(array $session, array $schedule, int $minutes): array
    {
        $shift = $schedule['shift'];

        return [
            'work_date' => $session['date']->toDateString(),
            'shift_code' => $shift?->shift_code,
            'schedule_start' => $this->formatClock($schedule['start'] ?? null),
            'schedule_end' => $this->formatClock($schedule['end'] ?? null),
            'time_in' => $this->formatClock($session['time_in'] ?? null),
            'time_out' => $this->formatClock($session['time_out'] ?? null),
            'minutes' => $minutes,
        ];
    }

    private function formatClock(?string $time): ?string
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        return substr(trim($time), 0, 5);
    }
}
