<?php

namespace App\Services;

use App\Models\EmployeeOvertimeApproval;
use App\Models\ShiftCode;
use App\Models\TimekeepingPolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Manual overtime filings are independent of timekeeping policy OT settings.
 * Validation uses time logs + shift schedule only (first In / last Out vs duty window).
 */
class EmployeeOvertimeApprovalService
{
    public function __construct(
        private readonly TimeLogsPayrollService $timeLogsPayroll,
        private readonly EmployeeShiftResolver $shiftResolver,
    ) {}

    /**
     * Validate OT window against punches + excess vs schedule; throws ValidationException on failure.
     * Policy is ignored for manual OT (kept in the signature for call-site compatibility).
     *
     * @return array{
     *     work_date: string,
     *     ot_start: CarbonImmutable,
     *     ot_end: CarbonImmutable,
     *     billable_minutes: int,
     *     excess_minutes: int,
     *     time_in: string|null,
     *     time_out: string|null
     * }
     */
    public function validateForStore(
        int $employeeId,
        string $workDate,
        string $otStartTime,
        string $otEndTime,
        ?TimekeepingPolicy $policy = null,
        ?ShiftCode $defaultShift = null,
    ): array {
        unset($policy);

        $sessionDate = CarbonImmutable::parse($workDate)->startOfDay();
        $startClock = substr(trim($otStartTime), 0, 5);
        $endClock = substr(trim($otEndTime), 0, 5);

        if ($startClock === $endClock) {
            throw ValidationException::withMessages([
                'ot_end' => 'OT start and OT end cannot be the same time. Use a range that covers excess hours (before shift start and/or after shift end).',
            ]);
        }

        $otStart = $this->combineDateAndTime($sessionDate, $otStartTime);
        $otEnd = $this->combineDateAndTime($sessionDate, $otEndTime);

        if ($otEnd->lessThanOrEqualTo($otStart)) {
            $otEnd = $otEnd->addDay();
        }

        if ($otEnd->lessThanOrEqualTo($otStart)) {
            throw ValidationException::withMessages([
                'ot_end' => 'Overtime end must be after overtime start.',
            ]);
        }

        $preview = $this->previewForDate($employeeId, $sessionDate->toDateString(), $defaultShift);

        if (! ($preview['ok'] ?? false)) {
            throw ValidationException::withMessages([
                'work_date' => (string) ($preview['message'] ?? 'Unable to file overtime for this date.'),
            ]);
        }

        $punchIn = CarbonImmutable::parse($preview['punch_in']);
        $punchOut = CarbonImmutable::parse($preview['punch_out']);

        if ($otStart->lessThan($punchIn) || $otEnd->greaterThan($punchOut)) {
            throw ValidationException::withMessages([
                'ot_start' => 'Overtime must fall within the employee time logs ('
                    .$punchIn->format('H:i').' – '.$punchOut->format('H:i').').',
            ]);
        }

        $excessWindows = array_map(
            static fn (array $window): array => [
                'start' => CarbonImmutable::parse($window['start']),
                'end' => CarbonImmutable::parse($window['end']),
            ],
            $preview['windows'] ?? [],
        );

        $excessMinutes = (int) ($preview['excess_minutes'] ?? 0);

        if ($excessMinutes <= 0 || $excessWindows === []) {
            throw ValidationException::withMessages([
                'work_date' => 'Employee has no excess hours on this date versus the scheduled shift. Overtime can only be filed against time outside the duty window.',
            ]);
        }

        $billable = $this->intersectionMinutes($otStart, $otEnd, $excessWindows);

        if ($billable <= 0) {
            $ranges = collect($preview['windows'] ?? [])
                ->map(static fn (array $window): string => ($window['label'] ?? ''))
                ->filter()
                ->implode(', ');

            throw ValidationException::withMessages([
                'ot_start' => 'Overtime must overlap excess hours outside the scheduled shift'
                    .($ranges !== '' ? ' (available: '.$ranges.')' : '').'.',
            ]);
        }

        return [
            'work_date' => $sessionDate->toDateString(),
            'ot_start' => $otStart,
            'ot_end' => $otEnd,
            'billable_minutes' => $billable,
            'excess_minutes' => $excessMinutes,
            'time_in' => $punchIn->format('H:i:s'),
            'time_out' => $punchOut->format('H:i:s'),
        ];
    }

    /**
     * Excess windows + suggested OT start/end for a work date (manual OT UI).
     *
     * @return array{
     *     ok: bool,
     *     message?: string,
     *     work_date?: string,
     *     punch_in?: string,
     *     punch_out?: string,
     *     schedule_start?: string|null,
     *     schedule_end?: string|null,
     *     excess_minutes?: int,
     *     suggested_ot_start?: string|null,
     *     suggested_ot_end?: string|null,
     *     windows?: list<array{start: string, end: string, label: string, minutes: int}>
     * }
     */
    public function previewForDate(
        int $employeeId,
        string $workDate,
        ?ShiftCode $defaultShift = null,
    ): array {
        $sessionDate = CarbonImmutable::parse($workDate)->startOfDay();
        $context = $this->sessionContext($employeeId, $sessionDate, $defaultShift);

        if ($context === null) {
            return [
                'ok' => false,
                'message' => 'No time logs found for this employee on the selected date.',
            ];
        }

        if ($context['time_in'] === null || $context['time_out'] === null
            || trim((string) $context['time_in']) === '' || trim((string) $context['time_out']) === '') {
            return [
                'ok' => false,
                'message' => 'Incomplete time logs (missing IN or OUT) for this date.',
            ];
        }

        if ($context['schedule_start'] === null || trim((string) $context['schedule_start']) === ''
            || $context['schedule_end'] === null || trim((string) $context['schedule_end']) === '') {
            return [
                'ok' => false,
                'message' => 'No shift schedule found for this date. Assign a shift code before filing overtime.',
            ];
        }

        $punchIn = $this->combineDateAndTime($sessionDate, $context['time_in']);
        $punchOut = $this->combineDateAndTime($sessionDate, $context['time_out']);
        if ($punchOut->lessThanOrEqualTo($punchIn)) {
            $punchOut = $punchOut->addDay();
        }

        $excessWindows = $this->excessWindows(
            $sessionDate,
            $punchIn,
            $punchOut,
            $context['schedule_start'],
            $context['schedule_end'],
        );

        $windows = [];
        $excessMinutes = 0;

        foreach ($excessWindows as $window) {
            $minutes = (int) $window['start']->diffInMinutes($window['end']);
            $excessMinutes += $minutes;
            $windows[] = [
                'start' => $window['start']->toDateTimeString(),
                'end' => $window['end']->toDateTimeString(),
                'label' => $window['start']->format('H:i').'–'.$window['end']->format('H:i'),
                'minutes' => $minutes,
            ];
        }

        if ($excessMinutes <= 0 || $windows === []) {
            return [
                'ok' => false,
                'work_date' => $sessionDate->toDateString(),
                'punch_in' => $punchIn->toDateTimeString(),
                'punch_out' => $punchOut->toDateTimeString(),
                'schedule_start' => $context['schedule_start'],
                'schedule_end' => $context['schedule_end'],
                'excess_minutes' => 0,
                'suggested_ot_start' => null,
                'suggested_ot_end' => null,
                'windows' => [],
                'message' => 'Employee has no excess hours on this date versus the scheduled shift.',
            ];
        }

        $first = $excessWindows[0];
        $last = $excessWindows[array_key_last($excessWindows)];

        return [
            'ok' => true,
            'work_date' => $sessionDate->toDateString(),
            'punch_in' => $punchIn->toDateTimeString(),
            'punch_out' => $punchOut->toDateTimeString(),
            'schedule_start' => $context['schedule_start'],
            'schedule_end' => $context['schedule_end'],
            'excess_minutes' => $excessMinutes,
            'suggested_ot_start' => $first['start']->format('H:i'),
            'suggested_ot_end' => $last['end']->format('H:i'),
            'windows' => $windows,
        ];
    }

    /**
     * Approved OT billable minutes keyed by work date (Y-m-d).
     *
     * @return array<string, int>
     */
    public function billableMinutesByDate(
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?ShiftCode $defaultShift = null,
    ): array {
        $rows = $this->dayBreakdownForPeriod($employeeId, $from, $to, $defaultShift);
        $byDate = [];

        foreach ($rows as $row) {
            $dateKey = (string) ($row['work_date'] ?? '');
            if ($dateKey === '') {
                continue;
            }
            $byDate[$dateKey] = (int) ($row['minutes'] ?? 0);
        }

        return $byDate;
    }

    /**
     * Total billable OT minutes from approvals ∩ excess for the pay period.
     * Manual OT pay ignores timekeeping policy OT flags / equivalents / rounding.
     */
    public function totalApprovedBillableMinutes(
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?TimekeepingPolicy $policy = null,
        ?ShiftCode $defaultShift = null,
    ): int {
        unset($policy);

        $approvals = EmployeeOvertimeApproval::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->orderBy('work_date')
            ->orderBy('ot_start')
            ->get();

        if ($approvals->isEmpty()) {
            return 0;
        }

        $this->shiftResolver->loadOverridesForRange($employeeId, $from, $to);

        $sessionsByDate = $this->timeLogsPayroll
            ->daySessionsForPeriod($employeeId, $from, $to)
            ->keyBy(fn (array $session) => $session['date']->toDateString());

        $rawTotal = 0;

        foreach ($approvals as $approval) {
            $dateKey = $approval->work_date?->toDateString();
            if ($dateKey === null || ! $sessionsByDate->has($dateKey)) {
                continue;
            }

            $session = $sessionsByDate->get($dateKey);
            $sessionDate = $session['date'];
            $dayShift = $this->shiftResolver->forDate($employeeId, $sessionDate, $defaultShift);

            if ($dayShift !== null && (bool) $dayShift->is_flexi_time) {
                continue;
            }

            $timeIn = $session['time_in'] ?? null;
            $timeOut = $session['time_out'] ?? null;
            if ($timeIn === null || $timeOut === null || $timeIn === '' || $timeOut === '') {
                continue;
            }

            $scheduleStart = $dayShift?->time_in ?? $defaultShift?->time_in;
            $scheduleEnd = $dayShift?->time_out ?? $defaultShift?->time_out;

            if ($scheduleStart === null || trim((string) $scheduleStart) === ''
                || $scheduleEnd === null || trim((string) $scheduleEnd) === '') {
                continue;
            }

            $punchIn = $this->combineDateAndTime($sessionDate, $timeIn);
            $punchOut = $this->combineDateAndTime($sessionDate, $timeOut);
            if ($punchOut->lessThanOrEqualTo($punchIn)) {
                $punchOut = $punchOut->addDay();
            }

            $otStart = CarbonImmutable::parse($approval->ot_start);
            $otEnd = CarbonImmutable::parse($approval->ot_end);

            $excessWindows = $this->excessWindows(
                $sessionDate,
                $punchIn,
                $punchOut,
                $scheduleStart,
                $scheduleEnd,
            );

            $rawTotal += $this->intersectionMinutes($otStart, $otEnd, $excessWindows);
        }

        return max(0, $rawTotal);
    }

    /**
     * Per-day approved OT minutes for payroll detail modals.
     *
     * @return list<array{
     *     work_date: string,
     *     shift_code: string|null,
     *     schedule_start: string|null,
     *     schedule_end: string|null,
     *     time_in: string|null,
     *     time_out: string|null,
     *     minutes: int,
     *     ot_start: string|null,
     *     ot_end: string|null
     * }>
     */
    public function dayBreakdownForPeriod(
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?ShiftCode $defaultShift = null,
    ): array {
        $approvals = EmployeeOvertimeApproval::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->orderBy('work_date')
            ->orderBy('ot_start')
            ->get();

        if ($approvals->isEmpty()) {
            return [];
        }

        $this->shiftResolver->loadOverridesForRange($employeeId, $from, $to);

        $sessionsByDate = $this->timeLogsPayroll
            ->daySessionsForPeriod($employeeId, $from, $to)
            ->keyBy(fn (array $session) => $session['date']->toDateString());

        /** @var array<string, array<string, mixed>> $byDate */
        $byDate = [];

        foreach ($approvals as $approval) {
            $dateKey = $approval->work_date?->toDateString();

            if ($dateKey === null || ! $sessionsByDate->has($dateKey)) {
                continue;
            }

            $session = $sessionsByDate->get($dateKey);
            $sessionDate = $session['date'];
            $dayShift = $this->shiftResolver->forDate($employeeId, $sessionDate, $defaultShift);

            if ($dayShift !== null && (bool) $dayShift->is_flexi_time) {
                continue;
            }

            $timeIn = $session['time_in'] ?? null;
            $timeOut = $session['time_out'] ?? null;

            if ($timeIn === null || $timeOut === null || $timeIn === '' || $timeOut === '') {
                continue;
            }

            $scheduleStart = $dayShift?->time_in ?? $defaultShift?->time_in;
            $scheduleEnd = $dayShift?->time_out ?? $defaultShift?->time_out;

            if ($scheduleStart === null || trim((string) $scheduleStart) === ''
                || $scheduleEnd === null || trim((string) $scheduleEnd) === '') {
                continue;
            }

            $punchIn = $this->combineDateAndTime($sessionDate, $timeIn);
            $punchOut = $this->combineDateAndTime($sessionDate, $timeOut);

            if ($punchOut->lessThanOrEqualTo($punchIn)) {
                $punchOut = $punchOut->addDay();
            }

            $otStart = CarbonImmutable::parse($approval->ot_start);
            $otEnd = CarbonImmutable::parse($approval->ot_end);

            $minutes = $this->intersectionMinutes(
                $otStart,
                $otEnd,
                $this->excessWindows($sessionDate, $punchIn, $punchOut, $scheduleStart, $scheduleEnd),
            );

            if ($minutes <= 0) {
                continue;
            }

            if (! isset($byDate[$dateKey])) {
                $byDate[$dateKey] = [
                    'work_date' => $dateKey,
                    'shift_code' => $dayShift?->shift_code ?? $defaultShift?->shift_code,
                    'schedule_start' => $this->formatClock($scheduleStart),
                    'schedule_end' => $this->formatClock($scheduleEnd),
                    'time_in' => $this->formatClock($timeIn),
                    'time_out' => $this->formatClock($timeOut),
                    'minutes' => 0,
                    'ot_start' => $otStart->format('H:i'),
                    'ot_end' => $otEnd->format('H:i'),
                ];
            }

            $byDate[$dateKey]['minutes'] += $minutes;

            $startClock = $otStart->format('H:i');
            $endClock = $otEnd->format('H:i');

            if ($startClock < ($byDate[$dateKey]['ot_start'] ?? $startClock)) {
                $byDate[$dateKey]['ot_start'] = $startClock;
            }

            if ($endClock > ($byDate[$dateKey]['ot_end'] ?? $endClock)) {
                $byDate[$dateKey]['ot_end'] = $endClock;
            }
        }

        return array_values($byDate);
    }

    private function formatClock(?string $time): ?string
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        return substr(trim($time), 0, 5);
    }

    /**
     * @return array{
     *     time_in: string|null,
     *     time_out: string|null,
     *     schedule_start: string|null,
     *     schedule_end: string|null
     * }|null
     */
    private function sessionContext(
        int $employeeId,
        CarbonImmutable $sessionDate,
        ?ShiftCode $defaultShift,
    ): ?array {
        $sessions = $this->timeLogsPayroll->daySessionsForPeriod(
            $employeeId,
            $sessionDate->startOfDay(),
            $sessionDate->endOfDay(),
        );

        $session = $sessions->first(
            fn (array $row) => $row['date']->toDateString() === $sessionDate->toDateString()
        );

        if ($session === null) {
            return null;
        }

        $dayShift = $this->shiftResolver->forDate($employeeId, $sessionDate, $defaultShift);

        return [
            'time_in' => $session['time_in'] ?? null,
            'time_out' => $session['time_out'] ?? null,
            'schedule_start' => $dayShift?->time_in ?? $defaultShift?->time_in,
            'schedule_end' => $dayShift?->time_out ?? $defaultShift?->time_out,
        ];
    }

    /**
     * Excess = punch time outside the scheduled duty window (always before + after).
     *
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function excessWindows(
        CarbonImmutable $sessionDate,
        CarbonImmutable $punchIn,
        CarbonImmutable $punchOut,
        ?string $scheduleStart,
        ?string $scheduleEnd,
    ): array {
        $windows = [];

        if ($scheduleStart) {
            try {
                $schedIn = $this->combineDateAndTime($sessionDate, $scheduleStart);
            } catch (\Throwable) {
                $schedIn = null;
            }

            if ($schedIn !== null && $punchIn->lessThan($schedIn)) {
                $windows[] = ['start' => $punchIn, 'end' => $schedIn];
            }
        }

        if ($scheduleEnd) {
            try {
                $schedOut = $this->combineDateAndTime($sessionDate, $scheduleEnd);
            } catch (\Throwable) {
                $schedOut = null;
            }

            if ($schedOut !== null && $punchOut->greaterThan($schedOut)) {
                if ($schedOut->lessThanOrEqualTo($punchIn)) {
                    $schedOut = $schedOut->addDay();
                }
                if ($punchOut->greaterThan($schedOut)) {
                    $windows[] = ['start' => $schedOut, 'end' => $punchOut];
                }
            }
        }

        return $windows;
    }

    /**
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable}>  $windows
     */
    private function intersectionMinutes(
        CarbonImmutable $otStart,
        CarbonImmutable $otEnd,
        array $windows,
    ): int {
        $total = 0;

        foreach ($windows as $window) {
            $start = $otStart->greaterThan($window['start']) ? $otStart : $window['start'];
            $end = $otEnd->lessThan($window['end']) ? $otEnd : $window['end'];

            if ($end->greaterThan($start)) {
                $total += (int) $start->diffInMinutes($end);
            }
        }

        return $total;
    }

    private function combineDateAndTime(CarbonImmutable $date, string $time): CarbonImmutable
    {
        $time = trim($time);

        if (preg_match('/^\d{1,2}:\d{2}$/', $time) === 1) {
            $time .= ':00';
        }

        return $date->setTimeFromTimeString($time);
    }
}
