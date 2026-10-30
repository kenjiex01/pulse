<?php

namespace App\Services;

use App\Models\BasicComputation;
use App\Support\PayrollCalendarWorkingDays;
use App\Models\EmployeeSalary;
use App\Models\RawTimekeepingInandout;
use App\Models\ShiftCode;
use App\Models\TimekeepingEmployeeSetup;
use App\Models\TimekeepingPolicy;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class TimeLogsPayrollService
{
    public function __construct(
        private readonly EmployeeLoadPayrollService $employeeLoadPayroll,
        private readonly PayrollBreakService $breakPayroll,
        private readonly EmployeeShiftResolver $shiftResolver,
    ) {}

    public function usesTimeLogs(EmployeeSalary $salary): bool
    {
        return (int) $salary->basic_computation_id === BasicComputation::TIME_IN_OUT;
    }

    /**
     * @return Collection<int, RawTimekeepingInandout>
     */
    public function punchesForEmployeeInPeriod(
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
    ): Collection {
        return RawTimekeepingInandout::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('dt_datetime', [
                $from->copy()->startOfDay(),
                $to->copy()->endOfDay(),
            ])
            ->orderBy('dt_datetime')
            ->orderBy('timekeeping_inandout_id')
            ->get();
    }

    public function hasPunchesInPeriod(
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
    ): bool {
        return RawTimekeepingInandout::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('dt_datetime', [
                $from->copy()->startOfDay(),
                $to->copy()->endOfDay(),
            ])
            ->exists();
    }

    /**
     * @return array{
     *     worked_days: int,
     *     basic_taxable: float,
     *     basic_non_taxable: float,
     *     late_minutes: int,
     *     late_deduction: float,
     *     undertime_minutes: int,
     *     undertime_deduction: float,
     *     absent_sessions: int
     * }
     */
    public function computeForPeriod(
        EmployeeSalary $salary,
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?TimekeepingPolicy $policy = null,
        ?string $scheduleStart = null,
        ?string $scheduleEnd = null,
        ?\App\Models\ShiftCode $shiftCode = null,
        bool $excludeExcessFromBasicHours = false,
        array $otMinutesByDate = [],
    ): array {
        $empty = [
            'worked_days' => 0,
            'basic_taxable' => 0.0,
            'basic_non_taxable' => 0.0,
            'late_minutes' => 0,
            'late_deduction' => 0.0,
            'undertime_minutes' => 0,
            'undertime_deduction' => 0.0,
            'absent_sessions' => 0,
            'half_day_absences' => 0,
            'absent_days' => 0.0,
            'absent_hours' => 0.0,
            'absent_deduction' => 0.0,
            'computed_hours' => 0.0,
            'late_days' => 0,
            'undertime_days' => 0,
            'ot_offset_minutes' => 0,
            'worked_dates' => [],
        ];

        if (! $this->usesTimeLogs($salary)) {
            return $empty;
        }

        $sessions = $this->daySessionsForPeriod($employeeId, $from, $to);

        if ($sessions->isEmpty()) {
            return $empty;
        }

        $this->shiftResolver->loadOverridesForRange($employeeId, $from, $to);

        $workedDays = 0;
        $workedDates = [];
        $computedHours = 0.0;
        $lateMinutes = 0;
        $undertimeMinutes = 0;
        $lateDays = 0;
        $undertimeDays = 0;
        $absentSessions = 0;
        $halfDayAbsences = 0;
        $halfDayAbsentMinutes = 0;
        $otOffsetMinutes = 0;

        foreach ($sessions as $session) {
            if ($session['time_in'] === null || $session['time_in'] === '') {
                continue;
            }

            $schedule = $this->scheduleForSession($employeeId, $session, $shiftCode);

            $dateKey = $session['date']->toDateString();
            $onRestDay = (bool) $salary->use_payroll_calendar_days
                && PayrollCalendarWorkingDays::isRestDay($employeeId, $session['date']);

            if ($schedule['shift'] !== null && (bool) $schedule['shift']->is_flexi_time) {
                $workedDays++;
                $workedDates[] = $dateKey;

                continue;
            }

            $otForDay = (int) ($otMinutesByDate[$dateKey] ?? 0);
            $resolvedLate = $this->resolvedLateForSession($session, $policy, $schedule['start'], $otForDay, $schedule['shift']);

            if ($resolvedLate['is_absent']) {
                if (! $onRestDay) {
                    $absentSessions++;
                }

                continue;
            }

            $workedDays++;
            $workedDates[] = $dateKey;
            $otOffsetMinutes += (int) ($resolvedLate['ot_offset_minutes'] ?? 0);

            if ($resolvedLate['is_half_day'] && ! $onRestDay) {
                $halfDayAbsences++;
                $halfDayAbsentMinutes += (int) ($resolvedLate['half_day_absent_minutes'] ?? 0);
            }

            if (($resolvedLate['use_full_schedule_hours'] ?? false) || $resolvedLate['is_half_day']) {
                $hours = $this->scheduledDutyHoursForSession(
                    $session,
                    $schedule['start'],
                    $schedule['end'],
                    $schedule['shift'],
                );
            } elseif ($excludeExcessFromBasicHours) {
                $hours = $this->regularHoursForSession(
                    $session,
                    $schedule['start'],
                    $schedule['end'],
                    $schedule['shift'],
                );
            } else {
                $hours = $this->punchHoursForSession($session);
            }

            if ($hours !== null) {
                $computedHours += $hours;
            }

            if ($resolvedLate['billable_minutes'] > 0) {
                $lateMinutes += $resolvedLate['billable_minutes'];
                $lateDays++;
            }

            $resolvedUndertime = $this->resolvedUndertimeForSession(
                $session,
                $policy,
                $schedule['end'],
                $schedule['start'],
            );
            if ($resolvedUndertime['billable_minutes'] > 0) {
                $undertimeMinutes += $resolvedUndertime['billable_minutes'];
                $undertimeDays++;
            }
        }

        $computedHours = round($computedHours, 4);
        $rateDays = PayrollCalendarWorkingDays::forEmployeeSalary($salary, $employeeId, $from, $to);

        if ($salary->use_basic_income_as_hourly_rate) {
            $hourlyRate = $salary->hourlyRate($rateDays);
            $basicTaxable = ($hourlyRate !== null && $computedHours > 0)
                ? round($computedHours * $hourlyRate, 2)
                : 0.0;
        } else {
            $dailyRate = $this->employeeLoadPayroll->dailyRate($salary, $rateDays);
            $basicTaxable = $dailyRate !== null
                ? round($dailyRate * $workedDays, 2)
                : 0.0;
            $computedHours = 0.0;
        }

        $hourlyRate = $salary->hourlyRate($rateDays);
        $lateDeduction = ($lateMinutes > 0 && $hourlyRate !== null)
            ? round(($lateMinutes / 60) * $hourlyRate, 2)
            : 0.0;
        $undertimeDeduction = ($undertimeMinutes > 0 && $hourlyRate !== null)
            ? round(($undertimeMinutes / 60) * $hourlyRate, 2)
            : 0.0;
        $absentHours = round($halfDayAbsentMinutes / 60, 4);
        $absentDays = round(0.5 * $halfDayAbsences, 4);

        return [
            'worked_days' => $workedDays,
            'worked_dates' => $workedDates,
            'basic_taxable' => $basicTaxable,
            'basic_non_taxable' => 0.0,
            'late_minutes' => $lateMinutes,
            'late_deduction' => $lateDeduction,
            'undertime_minutes' => $undertimeMinutes,
            'undertime_deduction' => $undertimeDeduction,
            'absent_sessions' => $absentSessions,
            'half_day_absences' => $halfDayAbsences,
            'absent_days' => $absentDays,
            'absent_hours' => $absentHours,
            'absent_deduction' => $this->halfDayAbsentPay($salary, $halfDayAbsences, $halfDayAbsentMinutes, $rateDays),
            'computed_hours' => round($computedHours, 4),
            'late_days' => $lateDays,
            'undertime_days' => $undertimeDays,
            'ot_offset_minutes' => $otOffsetMinutes,
        ];
    }

    /**
     * Session whose time in starts at the second half of the shift (half-day absence).
     *
     * @param  array{date: CarbonImmutable, time_in: string|null, time_out: string|null}  $session
     * @return array{date: CarbonImmutable, time_in: string|null, time_out: string|null}
     */
    public function sessionFromHalfDayStart(array $session, ?string $halfDayStart): array
    {
        if (! filled($halfDayStart) || ! filled($session['time_in'] ?? null)) {
            return $session;
        }

        try {
            $timeInAt = $session['date']->setTimeFromTimeString($session['time_in']);
            $startAt = $session['date']->setTimeFromTimeString($halfDayStart);
        } catch (\Throwable) {
            return $session;
        }

        if ($startAt->lessThan($timeInAt) && $timeInAt->diffInHours($startAt) > 12) {
            $startAt = $startAt->addDay();
        }

        if ($startAt->greaterThan($timeInAt)) {
            $session['time_in'] = $startAt->format('H:i:s');
        }

        return $session;
    }

    /**
     * @param  Collection<int, EmployeeSalary>  $salaries
     * @return array{
     *     worked_days: int,
     *     basic_taxable: float,
     *     basic_non_taxable: float,
     *     late_minutes: int,
     *     late_deduction: float,
     *     undertime_minutes: int,
     *     undertime_deduction: float,
     *     absent_sessions: int,
     *     late_days: int,
     *     undertime_days: int
     * }
     */
    public function computeForPeriodWithSalaries(
        Collection $salaries,
        EmployeeSalaryResolverService $resolver,
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?TimekeepingPolicy $policy = null,
        ?string $scheduleStart = null,
        ?string $scheduleEnd = null,
        ?\App\Models\ShiftCode $shiftCode = null,
        bool $excludeExcessFromBasicHours = false,
        array $otMinutesByDate = [],
    ): array {
        if ($salaries->isEmpty()) {
            return $this->computeForPeriod(new EmployeeSalary, $employeeId, $from, $to, $policy, $scheduleStart, $scheduleEnd, $shiftCode, $excludeExcessFromBasicHours, $otMinutesByDate);
        }

        if ($salaries->count() === 1) {
            return $this->computeForPeriod($salaries->first(), $employeeId, $from, $to, $policy, $scheduleStart, $scheduleEnd, $shiftCode, $excludeExcessFromBasicHours, $otMinutesByDate);
        }

        $primary = $salaries->last();

        if ((int) $primary->basic_computation_id !== BasicComputation::TIME_IN_OUT) {
            return $this->computeForPeriod($primary, $employeeId, $from, $to, $policy, $scheduleStart, $scheduleEnd, $shiftCode, $excludeExcessFromBasicHours, $otMinutesByDate);
        }

        $sessions = $this->daySessionsForPeriod($employeeId, $from, $to);

        if ($sessions->isEmpty()) {
            return $this->computeForPeriod($primary, $employeeId, $from, $to, $policy, $scheduleStart, $scheduleEnd, $shiftCode, $excludeExcessFromBasicHours, $otMinutesByDate);
        }

        $this->shiftResolver->loadOverridesForRange($employeeId, $from, $to);

        $workedDays = 0;
        $workedDates = [];
        $basicTaxable = 0.0;
        $computedHours = 0.0;
        $lateMinutes = 0;
        $lateDeduction = 0.0;
        $undertimeMinutes = 0;
        $undertimeDeduction = 0.0;
        $absentSessions = 0;
        $halfDayAbsences = 0;
        $halfDayAbsentMinutes = 0;
        $absentDeduction = 0.0;
        $lateDays = 0;
        $undertimeDays = 0;
        $otOffsetMinutes = 0;

        foreach ($sessions as $session) {
            $salary = $resolver->salaryEffectiveOnDate($salaries, $session['date']);

            if ($salary === null) {
                continue;
            }

            if ($session['time_in'] === null || $session['time_in'] === '') {
                continue;
            }

            $schedule = $this->scheduleForSession($employeeId, $session, $shiftCode);
            $dateKey = $session['date']->toDateString();
            $onRestDay = (bool) $salary->use_payroll_calendar_days
                && PayrollCalendarWorkingDays::isRestDay($employeeId, $session['date']);

            if ($schedule['shift'] !== null && (bool) $schedule['shift']->is_flexi_time) {
                $workedDays++;
                $workedDates[] = $dateKey;

                continue;
            }

            $otForDay = (int) ($otMinutesByDate[$dateKey] ?? 0);
            $resolvedLate = $this->resolvedLateForSession($session, $policy, $schedule['start'], $otForDay, $schedule['shift']);
            $resolvedUndertime = $this->resolvedUndertimeForSession(
                $session,
                $policy,
                $schedule['end'],
                $schedule['start'],
            );

            $rateDays = PayrollCalendarWorkingDays::forEmployeeSalary($salary, $employeeId, $from, $to);

            if (! $resolvedLate['is_absent']) {
                $workedDays++;
                $workedDates[] = $dateKey;
                $otOffsetMinutes += (int) ($resolvedLate['ot_offset_minutes'] ?? 0);

                if ($resolvedLate['is_half_day'] && ! $onRestDay) {
                    $halfDayAbsences++;
                    $halfDayAbsentMinutes += (int) ($resolvedLate['half_day_absent_minutes'] ?? 0);
                    $absentDeduction += $this->halfDayAbsentPay(
                        $salary,
                        1,
                        (int) ($resolvedLate['half_day_absent_minutes'] ?? 0),
                        $rateDays,
                    );
                }

                if ($salary->use_basic_income_as_hourly_rate) {
                    if (($resolvedLate['use_full_schedule_hours'] ?? false) || $resolvedLate['is_half_day']) {
                        $sessionHours = $this->scheduledDutyHoursForSession(
                            $session,
                            $schedule['start'],
                            $schedule['end'],
                            $schedule['shift'],
                        );
                    } else {
                        $sessionHours = $excludeExcessFromBasicHours
                            ? $this->regularHoursForSession($session, $schedule['start'], $schedule['end'], $schedule['shift'])
                            : $this->punchHoursForSession($session);
                    }
                    $hourlyRate = $salary->hourlyRate($rateDays);

                    if ($sessionHours !== null && $hourlyRate !== null) {
                        $basicTaxable += $sessionHours * $hourlyRate;
                        $computedHours += $sessionHours;
                    }
                } else {
                    $dailyRate = $this->employeeLoadPayroll->dailyRate($salary, $rateDays);

                    if ($dailyRate !== null) {
                        $basicTaxable += $dailyRate;
                    }
                }

                $lateMinutes += $resolvedLate['billable_minutes'];

                if ($resolvedLate['billable_minutes'] > 0) {
                    $lateDays++;
                }
            } else {
                if (! $onRestDay) {
                    $absentSessions++;
                }
            }

            $undertimeMinutes += $resolvedUndertime['billable_minutes'];

            if ($resolvedUndertime['billable_minutes'] > 0) {
                $undertimeDays++;
            }

            $hourlyRate = $salary->hourlyRate($rateDays);

            if ($hourlyRate !== null) {
                if ($resolvedLate['billable_minutes'] > 0 && ! $resolvedLate['is_absent']) {
                    $lateDeduction += ($resolvedLate['billable_minutes'] / 60) * $hourlyRate;
                }

                if ($resolvedUndertime['billable_minutes'] > 0) {
                    $undertimeDeduction += ($resolvedUndertime['billable_minutes'] / 60) * $hourlyRate;
                }
            }
        }

        return [
            'worked_days' => $workedDays,
            'worked_dates' => $workedDates,
            'basic_taxable' => round($basicTaxable, 2),
            'basic_non_taxable' => 0.0,
            'late_minutes' => $lateMinutes,
            'late_deduction' => round($lateDeduction, 2),
            'undertime_minutes' => $undertimeMinutes,
            'undertime_deduction' => round($undertimeDeduction, 2),
            'absent_sessions' => $absentSessions,
            'half_day_absences' => $halfDayAbsences,
            'absent_days' => round(0.5 * $halfDayAbsences, 4),
            'absent_hours' => round($halfDayAbsentMinutes / 60, 4),
            'absent_deduction' => round($absentDeduction, 2),
            'computed_hours' => round($computedHours, 4),
            'late_days' => $lateDays,
            'undertime_days' => $undertimeDays,
            'ot_offset_minutes' => $otOffsetMinutes,
        ];
    }

    /**
     * Daily work sessions for payroll.
     *
     * An OUT that falls after midnight still belongs to the previous shift when it
     * is inside that shift's buffer time out (shift end + buffer hours).
     *
     * @return Collection<int, array{date: CarbonImmutable, time_in: string|null, time_out: string|null}>
     */
    public function daySessionsForPeriod(
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
    ): Collection {
        return $this->dayPunchesForPeriod($employeeId, $from, $to)
            ->map(function (Collection $dayPunches, string $date) {
                $session = $this->workSessionFromPunches($dayPunches, CarbonImmutable::parse($date));

                return [
                    'date' => CarbonImmutable::parse($date),
                    'time_in' => $session['time_in'],
                    'time_out' => $session['time_out'],
                ];
            })
            ->sortKeys()
            ->values();
    }

    /**
     * @param  Collection<int, array{date: CarbonImmutable, time_in: string|null, time_out: string|null}>  $sessions
     */
    public function countWorkedDays(Collection $sessions, ?TimekeepingPolicy $policy, ?string $scheduleStart): int
    {
        return $sessions
            ->filter(function (array $session) use ($policy, $scheduleStart) {
                if ($session['time_in'] === null || $session['time_in'] === '') {
                    return false;
                }

                return ! $this->resolvedLateForSession($session, $policy, $scheduleStart)['is_absent'];
            })
            ->count();
    }

    /**
     * @param  Collection<int, array{date: CarbonImmutable, time_in: string|null, time_out: string|null}>  $sessions
     */
    public function countAbsentSessions(Collection $sessions, ?TimekeepingPolicy $policy, ?string $scheduleStart): int
    {
        $count = 0;

        foreach ($sessions as $session) {
            if ($this->resolvedLateForSession($session, $policy, $scheduleStart)['is_absent']) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  Collection<int, array{date: CarbonImmutable, time_in: string|null, time_out: string|null}>  $sessions
     */
    public function totalLateMinutes(Collection $sessions, ?TimekeepingPolicy $policy, ?string $scheduleStart): int
    {
        $total = 0;

        foreach ($sessions as $session) {
            $resolved = $this->resolvedLateForSession($session, $policy, $scheduleStart);

            if (! $resolved['is_absent']) {
                $total += $resolved['billable_minutes'];
            }
        }

        return $total;
    }

    /**
     * @param  Collection<int, array{date: CarbonImmutable, time_in: string|null, time_out: string|null}>  $sessions
     */
    public function totalUndertimeMinutes(Collection $sessions, ?TimekeepingPolicy $policy, ?string $scheduleEnd): int
    {
        $total = 0;

        foreach ($sessions as $session) {
            $total += $this->resolvedUndertimeForSession($session, $policy, $scheduleEnd)['billable_minutes'];
        }

        return $total;
    }

    /**
     * @param  Collection<int, array{date: CarbonImmutable, time_in: string|null, time_out: string|null}>  $sessions
     */
    public function countLateDays(Collection $sessions, ?TimekeepingPolicy $policy, ?string $scheduleStart): int
    {
        $count = 0;

        foreach ($sessions as $session) {
            $resolved = $this->resolvedLateForSession($session, $policy, $scheduleStart);

            if (! $resolved['is_absent'] && $resolved['billable_minutes'] > 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  Collection<int, array{date: CarbonImmutable, time_in: string|null, time_out: string|null}>  $sessions
     */
    public function countUndertimeDays(Collection $sessions, ?TimekeepingPolicy $policy, ?string $scheduleEnd): int
    {
        $count = 0;

        foreach ($sessions as $session) {
            if ($this->resolvedUndertimeForSession($session, $policy, $scheduleEnd)['billable_minutes'] > 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{
     *     raw_minutes: int,
     *     equivalent_minutes: int|null,
     *     is_absent: bool,
     *     billable_minutes: int,
     *     ot_offset_minutes: int,
     *     use_full_schedule_hours: bool,
     *     is_half_day: bool,
     *     half_day_absent_minutes: int,
     *     half_day_start: string|null
     * }
     */
    public function resolvedLateForSession(
        array $session,
        ?TimekeepingPolicy $policy,
        ?string $scheduleStart,
        int $otMinutesForDay = 0,
        ?ShiftCode $shift = null,
    ): array {
        $halfDay = $shift !== null ? $this->halfDayLateWindow($session, $policy, $scheduleStart, $shift) : null;

        if ($halfDay !== null) {
            return [
                'raw_minutes' => $this->lateRawMinutesForSession($session, $policy, $scheduleStart),
                'equivalent_minutes' => null,
                'is_absent' => false,
                'billable_minutes' => 0,
                'ot_offset_minutes' => 0,
                'use_full_schedule_hours' => false,
                'is_half_day' => true,
                'half_day_absent_minutes' => $halfDay['absent_minutes'],
                'half_day_start' => $halfDay['second_half_start'],
            ];
        }

        $raw = $this->lateRawMinutesForSession($session, $policy, $scheduleStart);
        $resolved = $this->employeeLoadPayroll->resolveLateMinutes($raw, $policy?->timekeeping_policy_id);

        return [...$this->applyAbsentOtOffset($resolved, $policy, $otMinutesForDay), ...$this->notHalfDay()];
    }

    /**
     * @return array{is_half_day: false, half_day_absent_minutes: 0, half_day_start: null}
     */
    private function notHalfDay(): array
    {
        return ['is_half_day' => false, 'half_day_absent_minutes' => 0, 'half_day_start' => null];
    }

    /**
     * Late longer than half the scheduled work time is a half-day absence, not late.
     * Basic then counts from the start of the second half (after a break at the midpoint)
     * to the end of the shift, e.g. 2PM–11PM with a 6–7PM break, in at 6:44PM → basic from 7PM.
     *
     * @param  array{date: CarbonImmutable, time_in: string|null, time_out: string|null}  $session
     * @return array{second_half_start: string, absent_minutes: int, second_half_minutes: int}|null
     */
    public function halfDayLateWindow(
        array $session,
        ?TimekeepingPolicy $policy,
        ?string $scheduleStart,
        \App\Models\ShiftCode $shift,
    ): ?array {
        if ((bool) $shift->is_flexi_time || ! filled($scheduleStart) || ! filled($shift->time_out) || ! filled($session['time_in'] ?? null)) {
            return null;
        }

        try {
            $startAt = $session['date']->setTimeFromTimeString($scheduleStart);
            $endAt = $session['date']->setTimeFromTimeString($shift->time_out);
            $timeInAt = $session['date']->setTimeFromTimeString($session['time_in']);
        } catch (\Throwable) {
            return null;
        }

        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt = $endAt->addDay();
        }

        $shift->loadMissing('breaks');
        $breaks = [];
        $locatedBreakMinutes = 0;

        foreach ($shift->breaks as $break) {
            if (! filled($break->break_out) || ! filled($break->break_in)) {
                continue;
            }

            try {
                $breakOut = $session['date']->setTimeFromTimeString($break->break_out);
                $breakIn = $session['date']->setTimeFromTimeString($break->break_in);
            } catch (\Throwable) {
                continue;
            }

            if ($breakOut->lessThan($startAt)) {
                $breakOut = $breakOut->addDay();
            }

            if ($breakIn->lessThanOrEqualTo($breakOut)) {
                $breakIn = $breakIn->addDay();
            }

            if ($breakOut->greaterThanOrEqualTo($endAt)) {
                continue;
            }

            $breakIn = $breakIn->greaterThan($endAt) ? $endAt : $breakIn;
            $breaks[] = [$breakOut, $breakIn];
            $locatedBreakMinutes += (int) $breakOut->diffInMinutes($breakIn);
        }

        usort($breaks, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $totalBreakMinutes = max($locatedBreakMinutes, $this->breakPayroll->scheduledBreakMinutes($shift));
        $unlocatedBreakMinutes = $totalBreakMinutes - $locatedBreakMinutes;
        $workMinutes = (int) $startAt->diffInMinutes($endAt) - $totalBreakMinutes;
        $halfMinutes = intdiv(max(0, $workMinutes), 2);

        if ($halfMinutes <= 0 || $timeInAt->greaterThanOrEqualTo($endAt)) {
            return null;
        }

        if ($this->lateRawMinutesForSession($session, $policy, $scheduleStart) <= $halfMinutes) {
            return null;
        }

        $cursor = $startAt;
        $remaining = $halfMinutes;

        foreach ($breaks as [$breakOut, $breakIn]) {
            $available = $breakOut->greaterThan($cursor) ? (int) $cursor->diffInMinutes($breakOut) : 0;

            if ($available >= $remaining) {
                break;
            }

            $remaining -= $available;
            $cursor = $breakIn->greaterThan($cursor) ? $breakIn : $cursor;
        }

        $secondHalfStart = $cursor->addMinutes($remaining);

        foreach ($breaks as [$breakOut, $breakIn]) {
            if ($secondHalfStart->equalTo($breakOut)) {
                $secondHalfStart = $breakIn;
            }
        }

        if ($breaks === [] && $unlocatedBreakMinutes > 0) {
            $secondHalfStart = $secondHalfStart->addMinutes($unlocatedBreakMinutes);
        }

        return [
            'second_half_start' => $secondHalfStart->format('H:i:s'),
            'absent_minutes' => $halfMinutes,
            'second_half_minutes' => max(0, $workMinutes - $halfMinutes),
        ];
    }

    /**
     * When policy offsets absent tardiness with OT: waive absent/late and claw 1 hour (60 min)
     * from OT — not the raw clock-in late minutes. Mark-as-absent means 1 hour absent.
     *
     * @param  array{raw_minutes: int, equivalent_minutes: int|null, is_absent: bool, billable_minutes: int}  $resolved
     * @return array{
     *     raw_minutes: int,
     *     equivalent_minutes: int|null,
     *     is_absent: bool,
     *     billable_minutes: int,
     *     ot_offset_minutes: int,
     *     use_full_schedule_hours: bool
     * }
     */
    public function applyAbsentOtOffset(
        array $resolved,
        ?TimekeepingPolicy $policy,
        int $otMinutesForDay,
    ): array {
        $resolved['ot_offset_minutes'] = 0;
        $resolved['use_full_schedule_hours'] = false;

        if (! ($resolved['is_absent'] ?? false)) {
            return $resolved;
        }

        if ($policy === null || ! (bool) $policy->is_offset_absent_tardiness_with_ot) {
            return $resolved;
        }

        if ($otMinutesForDay <= 0) {
            return $resolved;
        }

        // Mark-as-absent tardiness = 1 hour absent for OT offset (not raw late minutes).
        $absentHourMinutes = 60;
        $equivalent = (int) ($resolved['equivalent_minutes'] ?? 0);
        if ($equivalent > 0) {
            $absentHourMinutes = $equivalent;
        }

        $offset = min($absentHourMinutes, $otMinutesForDay);

        $resolved['is_absent'] = false;
        $resolved['billable_minutes'] = 0;
        $resolved['equivalent_minutes'] = null;
        $resolved['ot_offset_minutes'] = $offset;
        $resolved['use_full_schedule_hours'] = true;

        return $resolved;
    }

    /**
     * Full scheduled duty hours (ignores late punch-in) — used when absent+OT offset applies.
     *
     * @param  array{date: CarbonImmutable, time_in: string|null, time_out: string|null}  $session
     */
    public function scheduledDutyHoursForSession(
        array $session,
        ?string $scheduleStart,
        ?string $scheduleEnd,
        ?\App\Models\ShiftCode $shiftCode,
    ): ?float {
        if (! $this->hasValidDutySchedule($scheduleStart, $scheduleEnd, $shiftCode)) {
            return $this->punchHoursForSession($session);
        }

        try {
            $scheduledStart = $session['date']->setTimeFromTimeString($scheduleStart);
            $scheduledEnd = $session['date']->setTimeFromTimeString($scheduleEnd);

            if ($scheduledEnd->lessThanOrEqualTo($scheduledStart)) {
                $scheduledEnd = $scheduledEnd->addDay();
            }

            $minutes = (int) $scheduledStart->diffInMinutes($scheduledEnd);
            $minutes = max(0, $minutes - $this->breakPayroll->scheduledBreakMinutes($shiftCode));

            return round($minutes / 60, 4);
        } catch (\Throwable) {
            return $this->punchHoursForSession($session);
        }
    }

    /**
     * @param  array{date: CarbonImmutable, time_in: string|null, time_out: string|null}  $session
     * @return array{
     *     raw_minutes: int,
     *     equivalent_minutes: int|null,
     *     billable_minutes: int
     * }
     */
    public function resolvedUndertimeForSession(
        array $session,
        ?TimekeepingPolicy $policy,
        ?string $scheduleEnd,
        ?string $scheduleStart = null,
    ): array {
        $raw = $this->undertimeRawMinutesForSession($session, $scheduleEnd, $scheduleStart);

        return TimekeepingPolicySupport::resolveUndertimeEquivalent($policy?->timekeeping_policy_id, $raw);
    }

    /**
     * @param  array{date: CarbonImmutable, time_in: string|null, time_out: string|null}  $session
     */
    public function lateRawMinutesForSession(
        array $session,
        ?TimekeepingPolicy $policy,
        ?string $scheduleStart,
    ): int {
        if ($session['time_in'] === null || $session['time_in'] === '') {
            return 0;
        }

        if ($scheduleStart === null || trim($scheduleStart) === '') {
            return 0;
        }

        try {
            $scheduledAt = $session['date']->setTimeFromTimeString($scheduleStart);
            $timeInAt = $session['date']->setTimeFromTimeString($session['time_in']);
        } catch (\Throwable) {
            return 0;
        }

        $minutesToAdd = 0.0;

        if ($policy !== null) {
            $minutesToAdd += (float) ($policy->grace_period ?? 0);

            if ($policy->is_allow_flexi_time && (float) ($policy->max_flexi_time ?? 0) > 0) {
                $minutesToAdd += (float) $policy->max_flexi_time;
            }
        }

        $allowedUntil = $minutesToAdd > 0
            ? $scheduledAt->addMinutes((int) round($minutesToAdd))
            : $scheduledAt;

        if ($timeInAt->lessThanOrEqualTo($allowedUntil)) {
            return 0;
        }

        $subtractFrom = $minutesToAdd > 0 ? $allowedUntil : $scheduledAt;
        $lateMinutes = (int) $subtractFrom->diffInMinutes($timeInAt);

        if (
            $policy !== null
            && $policy->is_deduct_grace_period
            && $lateMinutes > 0
            && (float) ($policy->grace_period ?? 0) > 0
        ) {
            $lateMinutes += (int) round((float) $policy->grace_period);
        }

        return max(0, $lateMinutes);
    }

    /**
     * @param  array{date: CarbonImmutable, time_in: string|null, time_out: string|null}  $session
     */
    public function undertimeRawMinutesForSession(
        array $session,
        ?string $scheduleEnd,
        ?string $scheduleStart = null,
    ): int {
        if (($session['time_in'] ?? null) === null || $session['time_in'] === ''
            || $session['time_out'] === null || $session['time_out'] === '') {
            return 0;
        }

        if ($scheduleEnd === null || trim($scheduleEnd) === '') {
            return 0;
        }

        try {
            $scheduledEnd = $session['date']->setTimeFromTimeString($scheduleEnd);
            $timeOutAt = $session['date']->setTimeFromTimeString($session['time_out']);
            $timeInAt = filled($session['time_in'] ?? null)
                ? $session['date']->setTimeFromTimeString((string) $session['time_in'])
                : null;

            if ($scheduleStart !== null && trim($scheduleStart) !== '') {
                $scheduledStart = $session['date']->setTimeFromTimeString($scheduleStart);

                if ($scheduledEnd->lessThanOrEqualTo($scheduledStart)) {
                    $scheduledEnd = $scheduledEnd->addDay();
                }
            }

            // 00:15 after a 11:59 time-in is the next calendar day, not 12:15 AM the same morning.
            if ($timeInAt !== null && $timeOutAt->lessThanOrEqualTo($timeInAt)) {
                $timeOutAt = $timeOutAt->addDay();
            }
        } catch (\Throwable) {
            return 0;
        }

        if ($timeOutAt->greaterThanOrEqualTo($scheduledEnd)) {
            return 0;
        }

        return (int) $timeOutAt->diffInMinutes($scheduledEnd);
    }

    public function lateDeductionTypeId(): ?int
    {
        return $this->employeeLoadPayroll->lateDeductionTypeId();
    }

    public function undertimeDeductionTypeId(): ?int
    {
        return $this->employeeLoadPayroll->undertimeDeductionTypeId();
    }

    public function absentDeductionTypeId(): ?int
    {
        return $this->employeeLoadPayroll->absentDeductionTypeId();
    }

    /**
     * Half-day absence keeps full basic income; the first half is deducted as Absences (0.5 day).
     */
    private function halfDayAbsentPay(EmployeeSalary $salary, int $halfDayAbsences, int $halfDayAbsentMinutes, ?float $rateDays = null): float
    {
        if ($halfDayAbsences <= 0) {
            return 0.0;
        }

        if ($salary->use_basic_income_as_hourly_rate) {
            $hourlyRate = $salary->hourlyRate($rateDays);

            return ($hourlyRate !== null && $halfDayAbsentMinutes > 0)
                ? round(($halfDayAbsentMinutes / 60) * $hourlyRate, 2)
                : 0.0;
        }

        $dailyRate = $this->employeeLoadPayroll->dailyRate($salary, $rateDays);

        return $dailyRate !== null
            ? round($dailyRate * 0.5 * $halfDayAbsences, 2)
            : 0.0;
    }

    /**
     * @return Collection<string, Collection<int, RawTimekeepingInandout>>
     */
    public function dayPunchesForPeriod(
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
    ): Collection {
        $buffers = $this->logBuffersForEmployee($employeeId);

        if ($buffers['in_minutes'] <= 0 && $buffers['out_minutes'] <= 0) {
            return $this->calendarPunchGroups(
                $this->punchesForEmployeeInPeriod($employeeId, $from, $to),
            );
        }

        $fetchFrom = $from->copy()->startOfDay()->subMinutes($buffers['in_minutes']);
        $fetchTo = $to->copy()->endOfDay()->addMinutes($buffers['out_minutes']);

        $punches = RawTimekeepingInandout::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('dt_datetime', [$fetchFrom, $fetchTo])
            ->orderBy('dt_datetime')
            ->orderBy('timekeeping_inandout_id')
            ->get();

        $rangeStart = CarbonImmutable::parse($from->toDateString());
        $rangeEnd = CarbonImmutable::parse($to->toDateString());
        $this->shiftResolver->loadOverridesForRange(
            $employeeId,
            $rangeStart->subDay(),
            $rangeEnd->addDay(),
        );

        /** @var array<string, array{start: CarbonImmutable, end: CarbonImmutable}> $windows */
        $windows = [];
        $cursor = $rangeStart;

        while ($cursor->lte($rangeEnd)) {
            $shift = $this->shiftResolver->forDate($employeeId, $cursor, $buffers['shift']);
            $window = $this->logWindow($cursor, $shift, $buffers['in_minutes'], $buffers['out_minutes']);

            if ($window !== null) {
                $windows[$cursor->toDateString()] = $window;
            }

            $cursor = $cursor->addDay();
        }

        $remaining = $punches->keyBy(
            fn (RawTimekeepingInandout $punch) => (int) $punch->timekeeping_inandout_id,
        );
        $trimmed = collect();

        foreach ($windows as $date => $window) {
            $inWindow = $remaining->filter(function (RawTimekeepingInandout $punch) use ($window) {
                $at = $punch->dt_datetime;

                return $at !== null && $at->gte($window['start']) && $at->lte($window['end']);
            })->values();

            $session = $this->workSessionFromPunches($inWindow, CarbonImmutable::parse($date));

            if ($session['time_in'] === null || $session['punches']->isEmpty()) {
                continue;
            }

            $trimmed->put($date, $session['punches']);

            foreach ($session['punches'] as $punch) {
                $remaining->forget((int) $punch->timekeeping_inandout_id);
            }
        }

        $this->attachLeftoverIns($trimmed, $remaining->values(), $windows, $rangeStart, $rangeEnd);

        return $trimmed;
    }

    /**
     * An IN before the shift buffer still keeps the OUT that follows it, until the next shift window starts.
     *
     * @param  Collection<string, Collection<int, RawTimekeepingInandout>>  $trimmed
     * @param  Collection<int, RawTimekeepingInandout>  $remaining
     * @param  array<string, array{start: CarbonImmutable, end: CarbonImmutable}>  $windows
     */
    private function attachLeftoverIns(
        Collection $trimmed,
        Collection $remaining,
        array $windows,
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
    ): void {
        $remaining = $remaining->sortBy(fn (RawTimekeepingInandout $punch) => sprintf(
            '%s-%010d',
            $punch->dt_datetime?->format('Y-m-d H:i:s') ?? '',
            (int) $punch->timekeeping_inandout_id,
        ))->values();

        $used = [];

        foreach ($remaining as $punch) {
            if (isset($used[(int) $punch->timekeeping_inandout_id]) || ! (bool) $punch->is_in || $punch->dt_datetime === null) {
                continue;
            }

            $date = $punch->dt_datetime->toDateString();

            if ($date < $rangeStart->toDateString() || $date > $rangeEnd->toDateString() || $trimmed->has($date)) {
                continue;
            }

            $nextWindowStart = $windows[CarbonImmutable::parse($date)->addDay()->toDateString()]['start'] ?? null;
            $follow = $remaining->first(function (RawTimekeepingInandout $candidate) use ($punch, $nextWindowStart, $used) {
                if (isset($used[(int) $candidate->timekeeping_inandout_id]) || (bool) $candidate->is_in) {
                    return false;
                }

                if ($candidate->dt_datetime === null || $punch->dt_datetime === null || $candidate->dt_datetime->lte($punch->dt_datetime)) {
                    return false;
                }

                return $nextWindowStart === null || $candidate->dt_datetime->lt($nextWindowStart);
            });

            $bundle = collect([$punch]);

            if ($follow !== null) {
                $bundle->push($follow);
                $used[(int) $follow->timekeeping_inandout_id] = true;
            }

            $used[(int) $punch->timekeeping_inandout_id] = true;
            $session = $this->workSessionFromPunches($bundle, CarbonImmutable::parse($date));

            if ($session['time_in'] === null || $session['punches']->isEmpty()) {
                continue;
            }

            $trimmed->put($date, $session['punches']);
        }
    }

    /**
     * @return array{in_minutes: int, out_minutes: int, shift: ?ShiftCode}
     */
    private function logBuffersForEmployee(int $employeeId): array
    {
        $setup = TimekeepingEmployeeSetup::query()
            ->with(['policy', 'shiftCode'])
            ->where('employee_id', $employeeId)
            ->first();

        return [
            'in_minutes' => max(0, (int) round(((float) ($setup?->policy?->buffer_time_in ?? 0)) * 60)),
            'out_minutes' => max(0, (int) round(((float) ($setup?->policy?->buffer_time_out ?? 0)) * 60)),
            'shift' => $setup?->shiftCode,
        ];
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}|null
     */
    private function logWindow(
        CarbonImmutable $workDate,
        ?ShiftCode $shift,
        int $bufferInMinutes,
        int $bufferOutMinutes,
    ): ?array {
        if ($shift === null || (bool) $shift->is_flexi_time || $bufferOutMinutes <= 0) {
            return null;
        }

        $startText = trim((string) $shift->time_in);
        $endText = trim((string) $shift->time_out);

        if ($startText === '' || $endText === '' || ($startText === '00:00' && $endText === '00:00')) {
            return null;
        }

        try {
            $start = $workDate->setTimeFromTimeString($startText);
            $end = $workDate->setTimeFromTimeString($endText);
        } catch (\Throwable) {
            return null;
        }

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return [
            'start' => $start->subMinutes($bufferInMinutes),
            'end' => $end->addMinutes($bufferOutMinutes),
        ];
    }

    /**
     * @param  Collection<int, RawTimekeepingInandout>  $punches
     * @return Collection<string, Collection<int, RawTimekeepingInandout>>
     */
    private function calendarPunchGroups(Collection $punches): Collection
    {
        return $punches
            ->groupBy(fn (RawTimekeepingInandout $punch) => $punch->dt_datetime?->toDateString())
            ->filter(fn ($group, $date) => $date !== null && $date !== '')
            ->map(function (Collection $dayPunches, string $date) {
                return $this->workSessionFromPunches($dayPunches, CarbonImmutable::parse($date))['punches'];
            });
    }

    /**
     * First IN through the shift's real OUT. A same-day IN with no later OUT is still
     * the end of the session. An IN on the next calendar day does not replace an OUT
     * that already crossed midnight.
     *
     * @param  Collection<int, RawTimekeepingInandout>  $punches
     * @return array{
     *     time_in: string|null,
     *     time_out: string|null,
     *     punches: Collection<int, RawTimekeepingInandout>
     * }
     */
    private function workSessionFromPunches(Collection $punches, CarbonImmutable $workDate): array
    {
        $ordered = $punches->sortBy(fn (RawTimekeepingInandout $punch) => sprintf(
            '%s-%010d',
            $punch->dt_datetime?->format('Y-m-d H:i:s') ?? '',
            (int) $punch->timekeeping_inandout_id,
        ))->values();

        $firstIn = $ordered->first(fn (RawTimekeepingInandout $punch) => (bool) $punch->is_in);
        $lastOut = null;

        foreach ($ordered as $punch) {
            if ((bool) $punch->is_in) {
                continue;
            }

            if ($firstIn?->dt_datetime !== null && $punch->dt_datetime !== null && $punch->dt_datetime->lt($firstIn->dt_datetime)) {
                continue;
            }

            $lastOut = $punch;
        }

        $endPunch = $lastOut;
        $last = $ordered->last();

        if (
            $last instanceof RawTimekeepingInandout
            && $firstIn instanceof RawTimekeepingInandout
            && (bool) $last->is_in
            && (int) $last->timekeeping_inandout_id !== (int) $firstIn->timekeeping_inandout_id
            && $last->dt_datetime?->toDateString() === $workDate->toDateString()
            && ($lastOut === null || ($last->dt_datetime !== null && $lastOut->dt_datetime !== null && $last->dt_datetime->gt($lastOut->dt_datetime)))
        ) {
            $endPunch = $last;
        }

        $timeOut = null;

        if ($endPunch !== null && ($firstIn === null || (int) $endPunch->timekeeping_inandout_id !== (int) $firstIn->timekeeping_inandout_id)) {
            $timeOut = $endPunch->dt_datetime?->format('H:i:s');
        }

        $trimmed = $ordered;

        if ($endPunch?->dt_datetime !== null) {
            $endTs = $endPunch->dt_datetime->getTimestamp();
            $endId = (int) $endPunch->timekeeping_inandout_id;
            $trimmed = $ordered->filter(function (RawTimekeepingInandout $punch) use ($endTs, $endId) {
                return (int) $punch->timekeeping_inandout_id === $endId
                    || ($punch->dt_datetime !== null && $punch->dt_datetime->getTimestamp() <= $endTs);
            })->values();
        }

        return [
            'time_in' => $firstIn?->dt_datetime?->format('H:i:s'),
            'time_out' => $timeOut,
            'punches' => $trimmed,
        ];
    }

    public function totalBreakLateMinutes(
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?TimekeepingPolicy $policy,
        ?\App\Models\ShiftCode $shiftCode,
    ): int {
        if ($policy === null || ! $this->breakPayroll->deductsBreakTardiness($policy)) {
            return 0;
        }

        $this->shiftResolver->loadOverridesForRange($employeeId, $from, $to);
        $total = 0;

        foreach ($this->dayPunchesForPeriod($employeeId, $from, $to) as $dateKey => $dayPunches) {
            $date = CarbonImmutable::parse((string) $dateKey);
            $dayShift = $this->shiftResolver->forDate($employeeId, $date, $shiftCode);

            if ($dayShift !== null && (bool) $dayShift->is_flexi_time) {
                continue;
            }

            if ($this->breakPayroll->breakSegmentsFromPunches($dayPunches) === []) {
                continue;
            }

            $total += $this->breakPayroll->resolvedBreakLateMinutesForDay(
                $policy,
                $dayPunches,
                $dayShift,
                $date,
            )['billable_minutes'];
        }

        return $total;
    }

    /**
     * @param  array{date: CarbonImmutable, time_in: string|null, time_out: string|null}  $session
     * @return array{shift: ?\App\Models\ShiftCode, start: ?string, end: ?string}
     */
    public function scheduleForSession(
        int $employeeId,
        array $session,
        ?\App\Models\ShiftCode $defaultShift = null,
    ): array {
        $shift = $this->shiftResolver->forDate($employeeId, $session['date'], $defaultShift);

        return [
            'shift' => $shift,
            'start' => $shift?->time_in,
            'end' => $shift?->time_out,
        ];
    }

    /**
     * @param  Collection<int, array{date: CarbonImmutable, time_in: string|null, time_out: string|null}>  $sessions
     */
    public function totalPunchHoursForSessions(
        Collection $sessions,
        ?TimekeepingPolicy $policy,
        ?string $scheduleStart,
    ): float {
        $total = 0.0;

        foreach ($sessions as $session) {
            if ($session['time_in'] === null || $session['time_in'] === '') {
                continue;
            }

            if ($this->resolvedLateForSession($session, $policy, $scheduleStart)['is_absent']) {
                continue;
            }

            $hours = $this->punchHoursForSession($session);

            if ($hours !== null) {
                $total += $hours;
            }
        }

        return round($total, 4);
    }

    /**
     * Regular (schedule-bound) hours for basic pay — punch span clipped to duty schedule minus unpaid breaks.
     *
     * @param  Collection<int, array{date: CarbonImmutable, time_in: string|null, time_out: string|null}>  $sessions
     */
    public function totalRegularHoursForSessions(
        Collection $sessions,
        ?TimekeepingPolicy $policy,
        ?string $scheduleStart,
        ?string $scheduleEnd,
        ?\App\Models\ShiftCode $shiftCode,
    ): float {
        $total = 0.0;

        foreach ($sessions as $session) {
            if ($session['time_in'] === null || $session['time_in'] === '') {
                continue;
            }

            if ($this->resolvedLateForSession($session, $policy, $scheduleStart)['is_absent']) {
                continue;
            }

            $hours = $this->regularHoursForSession($session, $scheduleStart, $scheduleEnd, $shiftCode);

            if ($hours !== null) {
                $total += $hours;
            }
        }

        return round($total, 4);
    }

    /**
     * @param  array{date: CarbonImmutable, time_in: string|null, time_out: string|null}  $session
     */
    public function regularHoursForSession(
        array $session,
        ?string $scheduleStart,
        ?string $scheduleEnd,
        ?\App\Models\ShiftCode $shiftCode,
    ): ?float {
        $punchHours = $this->punchHoursForSession($session);

        if ($punchHours === null) {
            return null;
        }

        if (! $this->hasValidDutySchedule($scheduleStart, $scheduleEnd, $shiftCode)) {
            return $punchHours;
        }

        try {
            $scheduledStart = $session['date']->setTimeFromTimeString($scheduleStart);
            $scheduledEnd = $session['date']->setTimeFromTimeString($scheduleEnd);

            if ($scheduledEnd->lessThanOrEqualTo($scheduledStart)) {
                $scheduledEnd = $scheduledEnd->addDay();
            }

            $inAt = $session['date']->setTimeFromTimeString($session['time_in']);
            $outAt = $session['date']->setTimeFromTimeString($session['time_out']);

            if ($outAt->lessThanOrEqualTo($inAt)) {
                $outAt = $outAt->addDay();
            }

            $effectiveStart = $inAt->greaterThan($scheduledStart) ? $inAt : $scheduledStart;
            $effectiveEnd = $outAt->lessThan($scheduledEnd) ? $outAt : $scheduledEnd;

            if ($effectiveEnd->lessThanOrEqualTo($effectiveStart)) {
                return 0.0;
            }

            $regularMinutes = (int) $effectiveStart->diffInMinutes($effectiveEnd);
            $regularMinutes = max(0, $regularMinutes - $this->breakPayroll->scheduledBreakMinutes($shiftCode));

            return round($regularMinutes / 60, 4);
        } catch (\Throwable) {
            return $punchHours;
        }
    }

    /**
     * Regular hours from the second-half start (session time in) to the earlier of time out / shift end,
     * less only the scheduled breaks inside that window.
     *
     * @param  array{date: CarbonImmutable, time_in: string|null, time_out: string|null}  $session
     */
    public function halfDayRegularHoursForSession(
        array $session,
        ?string $scheduleStart,
        ?string $scheduleEnd,
        ?\App\Models\ShiftCode $shiftCode,
    ): ?float {
        if (! filled($session['time_in'] ?? null) || ! filled($session['time_out'] ?? null) || ! filled($scheduleStart) || ! filled($scheduleEnd)) {
            return $this->punchHoursForSession($session);
        }

        try {
            $scheduledStart = $session['date']->setTimeFromTimeString($scheduleStart);
            $scheduledEnd = $session['date']->setTimeFromTimeString($scheduleEnd);

            if ($scheduledEnd->lessThanOrEqualTo($scheduledStart)) {
                $scheduledEnd = $scheduledEnd->addDay();
            }

            $inAt = $session['date']->setTimeFromTimeString($session['time_in']);
            $outAt = $session['date']->setTimeFromTimeString($session['time_out']);

            if ($inAt->lessThan($scheduledStart)) {
                $inAt = $inAt->addDay();
            }

            if ($outAt->lessThanOrEqualTo($inAt)) {
                $outAt = $outAt->addDay();
            }

            $windowEnd = $outAt->lessThan($scheduledEnd) ? $outAt : $scheduledEnd;

            if ($windowEnd->lessThanOrEqualTo($inAt)) {
                return 0.0;
            }

            $minutes = (int) $inAt->diffInMinutes($windowEnd);

            foreach ($shiftCode?->breaks ?? [] as $break) {
                if (! filled($break->break_out) || ! filled($break->break_in)) {
                    continue;
                }

                $breakOut = $session['date']->setTimeFromTimeString($break->break_out);
                $breakIn = $session['date']->setTimeFromTimeString($break->break_in);

                if ($breakOut->lessThan($scheduledStart)) {
                    $breakOut = $breakOut->addDay();
                }

                if ($breakIn->lessThanOrEqualTo($breakOut)) {
                    $breakIn = $breakIn->addDay();
                }

                $overlapStart = $breakOut->greaterThan($inAt) ? $breakOut : $inAt;
                $overlapEnd = $breakIn->lessThan($windowEnd) ? $breakIn : $windowEnd;

                if ($overlapEnd->greaterThan($overlapStart)) {
                    $minutes -= (int) $overlapStart->diffInMinutes($overlapEnd);
                }
            }

            return round(max(0, $minutes) / 60, 4);
        } catch (\Throwable) {
            return $this->punchHoursForSession($session);
        }
    }

    private function hasValidDutySchedule(
        ?string $scheduleStart,
        ?string $scheduleEnd,
        ?\App\Models\ShiftCode $shiftCode,
    ): bool {
        if ($scheduleStart === null || trim($scheduleStart) === ''
            || $scheduleEnd === null || trim($scheduleEnd) === ''
            || ($scheduleStart === '00:00' && $scheduleEnd === '00:00')) {
            return false;
        }

        try {
            $start = CarbonImmutable::parse('2000-01-01 '.$scheduleStart);
            $end = CarbonImmutable::parse('2000-01-01 '.$scheduleEnd);

            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->addDay();
            }

            $scheduledMinutes = (int) $start->diffInMinutes($end);
            $paidMinutes = $scheduledMinutes - $this->breakPayroll->scheduledBreakMinutes($shiftCode);

            return $paidMinutes >= 120;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array{date: CarbonImmutable, time_in: string|null, time_out: string|null}  $session
     */
    public function punchHoursForSession(array $session): ?float
    {
        if ($session['time_in'] === null || $session['time_in'] === ''
            || $session['time_out'] === null || $session['time_out'] === '') {
            return null;
        }

        try {
            $inAt = $session['date']->setTimeFromTimeString($session['time_in']);
            $outAt = $session['date']->setTimeFromTimeString($session['time_out']);

            if ($outAt->lessThanOrEqualTo($inAt)) {
                $outAt = $outAt->addDay();
            }

            return max(0.0, round(((int) $inAt->diffInMinutes($outAt)) / 60, 4));
        } catch (\Throwable) {
            return null;
        }
    }
}
