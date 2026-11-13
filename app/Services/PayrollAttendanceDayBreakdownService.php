<?php

namespace App\Services;

use App\Models\PayrollAttendanceDay;
use App\Models\PayrollBatchDetail;
use App\Models\ShiftCode;
use App\Models\TimekeepingPolicy;
use App\Support\EmployeePayrollPeriod;
use App\Support\PayrollCalendarWorkingDays;
use Carbon\CarbonImmutable;
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
        private readonly EmployeeSalaryResolverService $salaryResolver,
    ) {}

    /**
     * @return array{
     *     LTDE: list<array<string, mixed>>,
     *     UTDE: list<array<string, mixed>>,
     *     OVRT: list<array<string, mixed>>,
     *     ABST: list<array<string, mixed>>
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

        $calendar = $detail->payrollBatch?->payrollCalendar;
        $from = $calendar?->dt_from;
        $to = $calendar?->dt_to;

        if ($from === null || $to === null || $detail->employee === null) {
            return ['LTDE' => [], 'UTDE' => [], 'OVRT' => [], 'ABST' => []];
        }

        $to = EmployeePayrollPeriod::effectiveEnd($detail->employee, $from, $to);

        if ($to === null) {
            return ['LTDE' => [], 'UTDE' => [], 'OVRT' => [], 'ABST' => []];
        }

        $savedDays = $detail->attendanceDays;

        if ($savedDays->isNotEmpty()) {
            $breakdown = $this->fromSavedDays($savedDays);
            $breakdown['ABST'] = $this->appendCalendarUnworkedAbsenceRows(
                $detail,
                $from,
                $to,
                $breakdown['ABST'],
                $savedDays,
            );

            return $breakdown;
        }

        $employeeId = (int) $detail->employee_id;
        $employee = $detail->employee;
        $policy = $employee?->timekeepingSetup?->policy;
        $defaultShift = $employee?->timekeepingSetup?->shiftCode;

        if (! $this->timeLogsPayroll->hasPunchesInPeriod($employeeId, $from, $to)) {
            $absent = $this->appendCalendarUnworkedAbsenceRows($detail, $from, $to, [], collect());

            return [
                'LTDE' => [],
                'UTDE' => [],
                'OVRT' => $this->overtimeApprovals->dayBreakdownForPeriod($employeeId, $from, $to, $defaultShift),
                'ABST' => $absent,
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

        $absentRows = $this->absenceDays($employeeId, $from, $to, $policy, $defaultShift);
        $absentRows = $this->appendCalendarUnworkedAbsenceRows($detail, $from, $to, $absentRows, collect());

        return [
            'LTDE' => $this->lateDays($employeeId, $from, $to, $policy, $defaultShift),
            'UTDE' => $this->undertimeDays($employeeId, $from, $to, $policy, $defaultShift),
            'OVRT' => $otRows,
            'ABST' => $absentRows,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PayrollAttendanceDay>  $savedDays
     * @return array{
     *     LTDE: list<array<string, mixed>>,
     *     UTDE: list<array<string, mixed>>,
     *     OVRT: list<array<string, mixed>>,
     *     ABST: list<array<string, mixed>>
     * }
     */
    private function fromSavedDays($savedDays): array
    {
        $late = [];
        $undertime = [];
        $overtime = [];
        $absent = [];

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

            $dayType = (string) ($day->day_type ?? '');

            if (strcasecmp($dayType, 'Absent') === 0) {
                $absent[] = $base + [
                    'absence_type' => 'Full day',
                    'days' => 1.0,
                    'minutes' => 0,
                ];
            } elseif (strcasecmp($dayType, 'Half-day Absent') === 0) {
                $absent[] = $base + [
                    'absence_type' => 'Half-day',
                    'days' => 0.5,
                    'minutes' => 0,
                ];
            }

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
            'ABST' => $absent,
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
                ->resolvedUndertimeForSession(
                    $session,
                    $policy,
                    $schedule['end'],
                    $schedule['start'],
                    $shift,
                    (bool) ($resolvedLate['is_half_day'] ?? false),
                )['billable_minutes'];

            if ($minutes <= 0) {
                continue;
            }

            $rows[] = $this->baseDayRow($session, $schedule, $minutes);
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function absenceDays(
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
            $schedule = $this->scheduleForAbsenceRow($employeeId, $session, $defaultShift);
            $shift = $schedule['shift'];
            $onRestDay = PayrollCalendarWorkingDays::isRestDay($employeeId, $session['date']);

            if ($onRestDay) {
                continue;
            }

            $dateKey = $session['date']->toDateString();
            $base = [
                'work_date' => $dateKey,
                'shift_code' => $shift?->shift_code,
                'schedule_start' => $this->formatClock($schedule['start'] ?? null),
                'schedule_end' => $this->formatClock($schedule['end'] ?? null),
                'time_in' => $this->formatClock($session['time_in'] ?? null),
                'time_out' => $this->formatClock($session['time_out'] ?? null),
            ];

            if ($shift !== null && (bool) $shift->is_flexi_time) {
                continue;
            }

            if (($session['time_in'] ?? null) === null || $session['time_in'] === '') {
                continue;
            }

            if ($this->timeLogsPayroll->sessionHasNoTimeOut($session)) {
                $rows[] = $base + [
                    'absence_type' => 'Full day',
                    'days' => 1.0,
                    'minutes' => 0,
                ];

                continue;
            }

            $resolvedLate = $this->timeLogsPayroll->resolvedLateForSession(
                $session,
                $policy,
                $schedule['start'],
                (int) ($otMinutesByDate[$dateKey] ?? 0),
                $shift,
            );

            if ($resolvedLate['is_absent']) {
                $rows[] = $base + [
                    'absence_type' => 'Full day',
                    'days' => 1.0,
                    'minutes' => 0,
                ];

                continue;
            }

            $resolvedUndertime = $this->timeLogsPayroll->resolvedUndertimeForSession(
                $session,
                $policy,
                $schedule['end'],
                $schedule['start'],
                $shift,
                (bool) ($resolvedLate['is_half_day'] ?? false),
            );

            $isHalfDay = ($resolvedLate['is_half_day'] ?? false) || ($resolvedUndertime['is_half_day'] ?? false);

            if (! $isHalfDay) {
                continue;
            }

            $rows[] = $base + [
                'absence_type' => 'Half-day',
                'days' => 0.5,
                'minutes' => 0,
            ];
        }

        return $rows;
    }

    /**
     * Full-day absences from payroll calendar days with no punch / not counted as worked.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  \Illuminate\Support\Collection<int, PayrollAttendanceDay>  $savedDays
     * @return list<array<string, mixed>>
     */
    private function appendCalendarUnworkedAbsenceRows(
        PayrollBatchDetail $detail,
        CarbonInterface $from,
        CarbonInterface $to,
        array $rows,
        $savedDays,
    ): array {
        $employee = $detail->employee;

        if ($employee === null) {
            return $rows;
        }

        $employeeId = (int) $detail->employee_id;
        $payTypeId = (int) ($detail->payrollBatch?->payrollCalendar?->pay_type_id ?? 0);

        if ($payTypeId <= 0) {
            return $rows;
        }

        $salaries = $this->salaryResolver->salariesForPeriod($employeeId, $payTypeId, $from, $to);
        $salary = $salaries->last();

        if ($salary === null || ! $salary->use_payroll_calendar_days) {
            return $rows;
        }

        $defaultShift = $employee->timekeepingSetup?->shiftCode;
        $restDayIds = PayrollCalendarWorkingDays::restDayIdsForEmployee($employeeId);
        $existingDates = array_fill_keys(array_column($rows, 'work_date'), true);

        $workedDates = [];

        if ($savedDays->isNotEmpty()) {
            foreach ($savedDays as $day) {
                $workDate = $day->work_date?->toDateString();

                if ($workDate === null) {
                    continue;
                }

                $dayType = (string) ($day->day_type ?? '');
                $isHalfDay = strcasecmp($dayType, 'Half-day Absent') === 0;

                // Match payroll unworked-absence: rest days, full absences, and days
                // with no real work session are not worked. A saved "Regular" row
                // with no punches is still an absence day.
                if (strcasecmp($dayType, 'Restday') === 0 || strcasecmp($dayType, 'Absent') === 0) {
                    continue;
                }

                if (! filled($day->time_in) || (! $isHalfDay && ! filled($day->time_out))) {
                    continue;
                }

                $workedDates[] = $workDate;
            }
        } else {
            $this->shiftResolver->loadOverridesForRange($employeeId, $from, $to);
            $sessions = $this->timeLogsPayroll->daySessionsForPeriod($employeeId, $from, $to);
            $policy = $employee->timekeepingSetup?->policy;
            $otMinutesByDate = $this->overtimeApprovals->billableMinutesByDate($employeeId, $from, $to, $defaultShift);

            foreach ($sessions as $session) {
                if (($session['time_in'] ?? null) === null || $session['time_in'] === '') {
                    continue;
                }

                if (PayrollCalendarWorkingDays::isRestDay($employeeId, $session['date'])) {
                    continue;
                }

                $schedule = $this->scheduleForAbsenceRow($employeeId, $session, $defaultShift);
                $shift = $schedule['shift'];

                if ($shift !== null && (bool) $shift->is_flexi_time) {
                    $workedDates[] = $session['date']->toDateString();

                    continue;
                }

                if ($this->timeLogsPayroll->sessionHasNoTimeOut($session)) {
                    continue;
                }

                $resolvedLate = $this->timeLogsPayroll->resolvedLateForSession(
                    $session,
                    $policy,
                    $schedule['start'],
                    (int) ($otMinutesByDate[$session['date']->toDateString()] ?? 0),
                    $shift,
                );

                if (! $resolvedLate['is_absent']) {
                    $workedDates[] = $session['date']->toDateString();
                }
            }
        }

        foreach (PayrollCalendarWorkingDays::absenceDates($from, $to, $restDayIds, $workedDates) as $dateKey) {
            if (isset($existingDates[$dateKey])) {
                continue;
            }

            $date = CarbonImmutable::parse($dateKey);
            $shift = $this->shiftResolver->forDate($employeeId, $date, $defaultShift);

            $rows[] = [
                'work_date' => $dateKey,
                'shift_code' => $shift?->shift_code,
                'schedule_start' => $this->formatClock($shift?->time_in),
                'schedule_end' => $this->formatClock($shift?->time_out),
                'time_in' => null,
                'time_out' => null,
                'absence_type' => 'Full day',
                'days' => 1.0,
                'minutes' => 0,
                'source' => 'Calendar',
            ];
            $existingDates[$dateKey] = true;
        }

        usort($rows, fn (array $a, array $b) => strcmp((string) $a['work_date'], (string) $b['work_date']));

        return array_values($rows);
    }

    /**
     * @param  array{date: CarbonImmutable, time_in: string|null, time_out: string|null}  $session
     * @return array{shift: ?ShiftCode, start: ?string, end: ?string}
     */
    private function scheduleForAbsenceRow(int $employeeId, array $session, ?ShiftCode $defaultShift): array
    {
        return $this->timeLogsPayroll->scheduleForSession($employeeId, $session, $defaultShift);
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
