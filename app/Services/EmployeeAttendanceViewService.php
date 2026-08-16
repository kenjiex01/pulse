<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollAttendanceDay;
use App\Models\PayrollBatchStatus;
use App\Models\RawTimekeepingInandout;
use App\Models\ShiftCode;
use App\Models\TimekeepingPolicy;
use App\Services\Reports\ReportGenerationResult;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PATHS-style daily Attendance View for Employee Profile.
 * OT / late / undertime / ND columns fill only when the day is covered by a processed payroll batch.
 */
class EmployeeAttendanceViewService
{
    public function __construct(
        private readonly TimeLogsPayrollService $timeLogsPayroll,
        private readonly EmployeeShiftResolver $shiftResolver,
        private readonly PayrollBreakService $breakPayroll,
        private readonly PayrollAttendanceDayBreakdownService $payrollDayBreakdown,
        private readonly PayrollOvertimeService $overtimePayroll,
    ) {}

    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     date_from: string,
     *     date_to: string,
     *     label: string,
     *     prev_year: int,
     *     prev_month: int,
     *     next_year: int,
     *     next_month: int,
     *     days: list<array<string, mixed>>
     * }
     */
    public function monthForEmployee(Employee $employee, int $year, int $month): array
    {
        $monthStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->endOfMonth()->startOfDay();

        return $this->rangeForEmployee(
            $employee,
            $monthStart->toDateString(),
            $monthEnd->toDateString(),
        );
    }

    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     date_from: string,
     *     date_to: string,
     *     label: string,
     *     prev_year: int,
     *     prev_month: int,
     *     next_year: int,
     *     next_month: int,
     *     days: list<array<string, mixed>>
     * }
     */
    public function rangeForEmployee(Employee $employee, string $dateFrom, string $dateTo): array
    {
        $from = CarbonImmutable::parse($dateFrom)->startOfDay();
        $to = CarbonImmutable::parse($dateTo)->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $prev = $from->subMonth()->startOfMonth();
        $next = $from->addMonth()->startOfMonth();

        $label = $from->equalTo($to)
            ? $from->format('M j, Y')
            : (
                $from->day === 1
                && $to->equalTo($from->endOfMonth()->startOfDay())
                    ? $from->format('F Y')
                    : ($from->format('Y-m') === $to->format('Y-m')
                        ? $from->format('M j').' – '.$to->format('j, Y')
                        : $from->format('M j, Y').' – '.$to->format('M j, Y'))
            );

        return [
            'year' => (int) $from->year,
            'month' => (int) $from->month,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'label' => $label,
            'prev_year' => (int) $prev->year,
            'prev_month' => (int) $prev->month,
            'next_year' => (int) $next->year,
            'next_month' => (int) $next->month,
            'days' => $this->daysForRange($employee, $from, $to),
        ];
    }

    /**
     * Build full per-day metrics for every date in the range (used when Process snapshots attendance).
     *
     * @return list<array<string, mixed>>
     */
    public function computeDaysForPersistence(Employee $employee, string $dateFrom, string $dateTo): array
    {
        return $this->buildDaysForRange($employee, $dateFrom, $dateTo, forceComputeMetrics: true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function daysForRange(Employee $employee, CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->buildDaysForRange(
            $employee,
            $from->toDateString(),
            $to->toDateString(),
            forceComputeMetrics: false,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildDaysForRange(
        Employee $employee,
        string $dateFrom,
        string $dateTo,
        bool $forceComputeMetrics,
    ): array {
        $employee->loadMissing([
            'timekeepingSetup.policy',
            'timekeepingSetup.shiftCode.breaks',
            'timekeepingRestDays',
        ]);

        $rangeStart = CarbonImmutable::parse($dateFrom)->startOfDay();
        $rangeEnd = CarbonImmutable::parse($dateTo)->startOfDay();

        if ($rangeEnd->lt($rangeStart)) {
            return [];
        }

        $queryFrom = $rangeStart;
        $queryTo = $rangeEnd->endOfDay();

        $employeeId = (int) $employee->employee_id;
        $policy = $employee->timekeepingSetup?->policy;
        $defaultShift = $employee->timekeepingSetup?->shiftCode;

        $restDayIds = $employee->timekeepingRestDays
            ->pluck('day_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->shiftResolver->loadOverridesForRange($employeeId, $queryFrom, $queryTo);

        $logs = RawTimekeepingInandout::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('dt_datetime', [$queryFrom, $queryTo])
            ->orderBy('dt_datetime')
            ->orderBy('timekeeping_inandout_id')
            ->get();

        /** @var Collection<string, Collection<int, RawTimekeepingInandout>> $logsByDate */
        $logsByDate = $logs->groupBy(fn (RawTimekeepingInandout $log) => $log->dt_datetime?->toDateString() ?? '');

        $sessions = $logsByDate
            ->filter(fn ($group, $date) => $date !== null && $date !== '')
            ->map(function (Collection $dayPunches, string $date) {
                $session = $this->breakPayroll->payrollSessionFromPunches($dayPunches);

                return [
                    'date' => CarbonImmutable::parse($date),
                    'time_in' => $session['time_in'],
                    'time_out' => $session['time_out'],
                ];
            });

        $savedDays = $forceComputeMetrics
            ? collect()
            : $this->savedAttendanceDaysForEmployee($employeeId, $rangeStart, $rangeEnd);

        $payrollDates = $forceComputeMetrics
            ? []
            : $this->processedPayrollDatesForEmployee($employeeId, $queryFrom, $queryTo);

        $needLiveMetricsDates = [];
        $cursor = $rangeStart;
        while ($cursor->lte($rangeEnd)) {
            $dateKey = $cursor->toDateString();
            $inPayroll = $forceComputeMetrics || isset($payrollDates[$dateKey]);
            if ($inPayroll && ! $savedDays->has($dateKey)) {
                $needLiveMetricsDates[$dateKey] = true;
            }
            $cursor = $cursor->addDay();
        }

        $otMinutesByDate = [];
        if ($needLiveMetricsDates !== []) {
            try {
                $otMinutesByDate = $this->payrollDayBreakdown->otMinutesByDate(
                    $employeeId,
                    $queryFrom,
                    $queryTo,
                    $policy,
                    $defaultShift,
                );
            } catch (\Throwable) {
                $otMinutesByDate = [];
            }
        }

        $days = [];
        $cursor = $rangeStart;

        while ($cursor->lte($rangeEnd)) {
            $dateKey = $cursor->toDateString();
            $dayLogs = $logsByDate->get($dateKey, collect())->values();
            $session = $sessions->get($dateKey);
            $shift = $this->shiftResolver->forDate($employeeId, $cursor, $defaultShift);
            $isRestDay = in_array((int) $cursor->dayOfWeek + 1, $restDayIds, true);
            /** @var PayrollAttendanceDay|null $saved */
            $saved = $savedDays->get($dateKey);
            $inPayroll = $forceComputeMetrics || $saved !== null || isset($payrollDates[$dateKey]);

            $timeIn = $session['time_in'] ?? null;
            $timeOut = $session['time_out'] ?? null;

            if ($saved !== null) {
                $metrics = [
                    'basic' => $this->decimalOrNull($saved->basic),
                    'excess_hours' => $this->decimalOrNull($saved->excess_hours),
                    'ot' => $this->decimalOrNull($saved->ot),
                    'sot' => $this->decimalOrNull($saved->sot),
                    'ndiff' => $this->decimalOrNull($saved->ndiff),
                    'ndot' => $this->decimalOrNull($saved->ndot),
                    'ndsot' => $this->decimalOrNull($saved->ndsot),
                    'late' => $this->decimalOrNull($saved->late),
                    'undertime' => $this->decimalOrNull($saved->undertime),
                    'break_late' => $this->decimalOrNull($saved->break_late),
                ];

                if ($saved->time_in) {
                    $timeIn = (string) $saved->time_in;
                }
                if ($saved->time_out) {
                    $timeOut = (string) $saved->time_out;
                }

                $isRestDay = strcasecmp((string) $saved->day_type, 'Restday') === 0;
                if ($saved->relationLoaded('shiftCode') && $saved->shiftCode) {
                    $shift = $saved->shiftCode;
                } elseif ($saved->shift_code_id) {
                    $shift = $saved->shiftCode ?? $shift;
                }
            } elseif ($inPayroll) {
                $metrics = $this->metricsForDay(
                    $employeeId,
                    $cursor,
                    $timeIn,
                    $timeOut,
                    $dayLogs,
                    $policy,
                    $shift,
                    (int) ($otMinutesByDate[$dateKey] ?? 0),
                );
            } else {
                $metrics = $this->emptyMetrics();
            }

            // Basic hours: show scheduled hours when punches exist (PATHS-like), even before payroll.
            if (! $inPayroll && $timeIn && ! $isRestDay) {
                $metrics['basic'] = $this->scheduledHours($cursor, $shift?->time_in, $shift?->time_out, $shift);
            }

            $days[] = [
                'date' => $dateKey,
                'date_label' => $cursor->format('m/d/Y'),
                'label' => $cursor->format('l, M j, Y'),
                'day_type' => $saved?->day_type ?? ($isRestDay ? 'Restday' : 'Regular'),
                'is_rest_day' => $isRestDay,
                'shift_code_id' => $shift?->shift_code_id ?? $saved?->shift_code_id,
                'shift_label' => $this->formatShiftLabel($shift),
                'time_in' => $this->formatPunchTime($timeIn),
                'time_out' => $this->formatPunchTime($timeOut),
                'time_in_raw' => $timeIn,
                'time_out_raw' => $timeOut,
                'has_logs' => $dayLogs->isNotEmpty(),
                'log_count' => $dayLogs->count(),
                'logs' => $dayLogs,
                'in_payroll_batch' => $inPayroll,
                'payroll_batch_id' => $saved?->payrollBatchDetail?->payroll_batch_id
                    ?? ($payrollDates[$dateKey] ?? null),
                'basic' => $metrics['basic'],
                'excess_hours' => $metrics['excess_hours'],
                'ot' => $metrics['ot'],
                'sot' => $metrics['sot'],
                'ndiff' => $metrics['ndiff'],
                'ndot' => $metrics['ndot'],
                'ndsot' => $metrics['ndsot'],
                'late' => $metrics['late'],
                'undertime' => $metrics['undertime'],
                'break_late' => $metrics['break_late'],
            ];

            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return Collection<string, PayrollAttendanceDay>
     */
    private function savedAttendanceDaysForEmployee(
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
    ): Collection {
        return PayrollAttendanceDay::query()
            ->with(['shiftCode', 'payrollBatchDetail'])
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('payrollBatchDetail.payrollBatch', function ($query) {
                $query->whereIn('payroll_batch_status_id', [
                    PayrollBatchStatus::PROCESSED,
                    PayrollBatchStatus::AWAITING_APPROVAL,
                    PayrollBatchStatus::POSTING,
                    PayrollBatchStatus::POSTED,
                ]);
            })
            ->orderBy('payroll_attendance_day_id')
            ->get()
            ->keyBy(fn (PayrollAttendanceDay $day) => $day->work_date?->toDateString() ?? '');
    }

    private function decimalOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * @return array<string, int> date => payroll_batch_id
     */
    private function processedPayrollDatesForEmployee(int $employeeId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = DB::table('trn_payroll_batch_details as d')
            ->join('trn_payroll_batches as b', 'b.payroll_batch_id', '=', 'd.payroll_batch_id')
            ->join('tbl_payroll_calendar as c', 'c.payroll_calendar_id', '=', 'b.payroll_calendar_id')
            ->where('d.employee_id', $employeeId)
            ->whereIn('b.payroll_batch_status_id', [
                PayrollBatchStatus::PROCESSED,
                PayrollBatchStatus::AWAITING_APPROVAL,
                PayrollBatchStatus::POSTING,
                PayrollBatchStatus::POSTED,
            ])
            ->whereDate('c.dt_from', '<=', $to->toDateString())
            ->whereDate('c.dt_to', '>=', $from->toDateString())
            ->get([
                'd.payroll_batch_id',
                'c.dt_from',
                'c.dt_to',
            ]);

        $map = [];

        foreach ($rows as $row) {
            $batchId = (int) $row->payroll_batch_id;
            if ($batchId <= 0 || $row->dt_from === null || $row->dt_to === null) {
                continue;
            }

            $cursor = CarbonImmutable::parse(Carbon::parse($row->dt_from)->toDateString());
            $end = CarbonImmutable::parse(Carbon::parse($row->dt_to)->toDateString());

            while ($cursor->lte($end)) {
                $key = $cursor->toDateString();

                if ($key >= $from->toDateString() && $key <= $to->toDateString()) {
                    $map[$key] = $batchId;
                }

                $cursor = $cursor->addDay();
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    public function pdfHeaders(): array
    {
        return [
            'Date',
            'Day Type',
            'Shift',
            'Time In',
            'Time Out',
            'Basic',
            'Excess Hours',
            'OT',
            'NDiff',
            'NDOT',
            'Late',
            'Undertime',
            'Break Late',
        ];
    }

    /**
     * @param  array<string, mixed>  $day
     * @return list<string>
     */
    public function pdfRowForDay(array $day): array
    {
        return [
            (string) ($day['date_label'] ?? ''),
            (string) ($day['day_type'] ?? ''),
            (string) ($day['shift_label'] ?? ''),
            ($day['is_rest_day'] ?? false) && ($day['time_in'] ?? '') === '—' ? '' : (string) ($day['time_in'] ?? ''),
            ($day['is_rest_day'] ?? false) && ($day['time_out'] ?? '') === '—' ? '' : (string) ($day['time_out'] ?? ''),
            $this->formatMetric($day['basic'] ?? null),
            $this->formatMetric($day['excess_hours'] ?? null),
            $this->formatMetric($day['ot'] ?? null),
            $this->formatMetric($day['ndiff'] ?? null),
            $this->formatMetric($day['ndot'] ?? null),
            $this->formatMetric($day['late'] ?? null),
            $this->formatMetric($day['undertime'] ?? null),
            $this->formatMetric($day['break_late'] ?? null),
        ];
    }

    public function pdfResultForEmployee(Employee $employee, string $dateFrom, string $dateTo): ReportGenerationResult
    {
        $attendance = $this->rangeForEmployee($employee, $dateFrom, $dateTo);
        $headers = $this->pdfHeaders();
        $rows = [];

        foreach ($attendance['days'] as $day) {
            $rows[] = $this->pdfRowForDay($day);
        }

        $employeeName = trim((string) ($employee->full_name ?: $employee->employee_number));
        $period = (string) ($attendance['label'] ?? '');

        return new ReportGenerationResult(
            title: 'Attendance View',
            headers: $headers,
            rows: $rows,
            meta: [
                'layout' => 'attendance_view',
                'filter_summary' => $employeeName.' ('.$employee->employee_number.') — '.$period,
                'period_label' => $period,
                'date_from' => $attendance['date_from'],
                'date_to' => $attendance['date_to'],
            ],
        );
    }

    /**
     * @param  Collection<int, RawTimekeepingInandout>  $dayPunches
     * @return array{
     *     basic: float|null,
     *     excess_hours: float|null,
     *     ot: float|null,
     *     sot: float|null,
     *     ndiff: float|null,
     *     ndot: float|null,
     *     ndsot: float|null,
     *     late: float|null,
     *     undertime: float|null,
     *     break_late: float|null
     * }
     */
    private function metricsForDay(
        int $employeeId,
        CarbonImmutable $date,
        ?string $timeIn,
        ?string $timeOut,
        Collection $dayPunches,
        ?TimekeepingPolicy $policy,
        ?ShiftCode $shift,
        int $approvedOtMinutes,
    ): array {
        $metrics = $this->emptyMetrics();

        if ($timeIn === null || $timeIn === '') {
            return $metrics;
        }

        $session = [
            'date' => $date,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
        ];

        $scheduleStart = $shift?->time_in;
        $scheduleEnd = $shift?->time_out;
        $isFlexi = $shift !== null && (bool) $shift->is_flexi_time;

        $lateMinutes = 0;
        $undertimeMinutes = 0;
        $breakLateMinutes = 0;

        if (! $isFlexi) {
            $lateResolved = $this->timeLogsPayroll->resolvedLateForSession(
                $session,
                $policy,
                $scheduleStart,
                $approvedOtMinutes,
            );

            if (! $lateResolved['is_absent'] && (int) ($lateResolved['ot_offset_minutes'] ?? 0) === 0) {
                $lateMinutes = (int) $lateResolved['billable_minutes'];
            }

            $undertimeMinutes = (int) $this->timeLogsPayroll->resolvedUndertimeForSession(
                $session,
                $policy,
                $scheduleEnd,
            )['billable_minutes'];

            if ($policy !== null && $this->breakPayroll->deductsBreakTardiness($policy) && $dayPunches->isNotEmpty()) {
                $actualBreak = $this->breakPayroll->actualBreakMinutesFromPunches($dayPunches);

                if ($actualBreak > 0) {
                    $breakLateMinutes = $this->breakPayroll->resolvedBreakLateMinutes(
                        $policy,
                        $actualBreak,
                        $this->breakPayroll->scheduledBreakMinutes($shift),
                    )['billable_minutes'];
                }
            }
        }

        $scheduled = $this->scheduledHours($date, $scheduleStart, $scheduleEnd, $shift);
        $deductionHours = ($lateMinutes + $undertimeMinutes + $breakLateMinutes) / 60;
        $basic = max(0.0, round($scheduled - $deductionHours, 2));

        $otBreakdown = $this->overtimePayroll->billableMinutesBreakdownForSession(
            $date,
            $timeIn,
            $timeOut,
            $scheduleStart,
            $scheduleEnd,
            $policy,
        );

        $excessMinutes = (int) ($otBreakdown['regular_minutes'] ?? 0) + (int) ($otBreakdown['special_minutes'] ?? 0);
        $otHours = round($approvedOtMinutes / 60, 2);
        $sotHours = round(((int) ($otBreakdown['special_minutes'] ?? 0)) / 60, 2);

        // NDIF / NDOT / NDSOT: show ND basic from policy window when punches overlap; OT variants stay 0 until dedicated split exists.
        $ndiffHours = $this->nightDiffHours($date, $timeIn, $timeOut, $policy);

        $metrics['basic'] = $basic;
        $metrics['excess_hours'] = round($excessMinutes / 60, 2);
        $metrics['ot'] = $otHours;
        $metrics['sot'] = $sotHours > 0 ? $sotHours : null;
        $metrics['ndiff'] = $ndiffHours > 0 ? round($ndiffHours, 2) : null;
        $metrics['ndot'] = null;
        $metrics['ndsot'] = null;
        $metrics['late'] = $lateMinutes > 0 ? round($lateMinutes / 60, 2) : 0.0;
        $metrics['undertime'] = $undertimeMinutes > 0 ? round($undertimeMinutes / 60, 2) : 0.0;
        $metrics['break_late'] = $breakLateMinutes > 0 ? round($breakLateMinutes / 60, 2) : 0.0;

        return $metrics;
    }

    /**
     * @return array{
     *     basic: float|null,
     *     excess_hours: float|null,
     *     ot: float|null,
     *     sot: float|null,
     *     ndiff: float|null,
     *     ndot: float|null,
     *     ndsot: float|null,
     *     late: float|null,
     *     undertime: float|null,
     *     break_late: float|null
     * }
     */
    private function emptyMetrics(): array
    {
        return [
            'basic' => null,
            'excess_hours' => null,
            'ot' => null,
            'sot' => null,
            'ndiff' => null,
            'ndot' => null,
            'ndsot' => null,
            'late' => null,
            'undertime' => null,
            'break_late' => null,
        ];
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

            return max(0.0, round($scheduledMinutes / 60, 2));
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function nightDiffHours(
        CarbonImmutable $sessionDate,
        ?string $timeIn,
        ?string $timeOut,
        ?TimekeepingPolicy $policy,
    ): float {
        if ($policy === null || $timeIn === null || $timeIn === '' || $timeOut === null || $timeOut === '') {
            return 0.0;
        }

        $ndStart = $policy->night_diff_start ?? null;
        $ndEnd = $policy->night_diff_end ?? null;

        if ($ndStart === null || $ndEnd === null) {
            return 0.0;
        }

        try {
            $inAt = $sessionDate->setTimeFromTimeString($timeIn);
            $outAt = $sessionDate->setTimeFromTimeString($timeOut);

            if ($outAt->lessThanOrEqualTo($inAt)) {
                $outAt = $outAt->addDay();
            }

            $windowStart = $sessionDate->setTimeFromTimeString((string) $ndStart);
            $windowEnd = $sessionDate->setTimeFromTimeString((string) $ndEnd);

            if ($windowEnd->lessThanOrEqualTo($windowStart)) {
                $windowEnd = $windowEnd->addDay();
            }

            $overlapStart = $inAt->greaterThan($windowStart) ? $inAt : $windowStart;
            $overlapEnd = $outAt->lessThan($windowEnd) ? $outAt : $windowEnd;

            if ($overlapEnd->lessThanOrEqualTo($overlapStart)) {
                return 0.0;
            }

            return ((int) $overlapStart->diffInMinutes($overlapEnd)) / 60;
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function formatShiftLabel(?ShiftCode $shift): string
    {
        if ($shift === null) {
            return '—';
        }

        $start = $this->formatPunchTime($shift->time_in);
        $end = $this->formatPunchTime($shift->time_out);
        $code = trim((string) ($shift->shift_code ?? ''));

        if ($start === '—' || $end === '—') {
            return $code !== '' ? $code : '—';
        }

        return trim($start.' to '.$end.($code !== '' ? ' ('.$code.')' : ''));
    }

    private function formatPunchTime(?string $time): string
    {
        if ($time === null || $time === '') {
            return '—';
        }

        try {
            return Carbon::parse('2000-01-01 '.$time)->format('H:i');
        } catch (\Throwable) {
            return '—';
        }
    }

    private function formatMetric(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 2, '.', '');
    }
}
