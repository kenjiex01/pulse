<?php

namespace App\Services;

use App\Models\BasicComputation;
use App\Models\EmployeeSalary;
use App\Models\RawTimekeepingInandout;
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
            'computed_hours' => 0.0,
            'late_days' => 0,
            'undertime_days' => 0,
            'ot_offset_minutes' => 0,
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
        $computedHours = 0.0;
        $lateMinutes = 0;
        $undertimeMinutes = 0;
        $lateDays = 0;
        $undertimeDays = 0;
        $absentSessions = 0;
        $otOffsetMinutes = 0;

        foreach ($sessions as $session) {
            if ($session['time_in'] === null || $session['time_in'] === '') {
                continue;
            }

            $schedule = $this->scheduleForSession($employeeId, $session, $shiftCode);

            if ($schedule['shift'] !== null && (bool) $schedule['shift']->is_flexi_time) {
                $workedDays++;

                continue;
            }

            $dateKey = $session['date']->toDateString();
            $otForDay = (int) ($otMinutesByDate[$dateKey] ?? 0);
            $resolvedLate = $this->resolvedLateForSession($session, $policy, $schedule['start'], $otForDay);

            if ($resolvedLate['is_absent']) {
                $absentSessions++;

                continue;
            }

            $workedDays++;
            $otOffsetMinutes += (int) ($resolvedLate['ot_offset_minutes'] ?? 0);

            if ($resolvedLate['use_full_schedule_hours'] ?? false) {
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

            $resolvedUndertime = $this->resolvedUndertimeForSession($session, $policy, $schedule['end']);
            if ($resolvedUndertime['billable_minutes'] > 0) {
                $undertimeMinutes += $resolvedUndertime['billable_minutes'];
                $undertimeDays++;
            }
        }

        $computedHours = round($computedHours, 4);

        if ($salary->use_basic_income_as_hourly_rate) {
            $hourlyRate = $salary->hourlyRate();
            $basicTaxable = ($hourlyRate !== null && $computedHours > 0)
                ? round($computedHours * $hourlyRate, 2)
                : 0.0;
        } else {
            $dailyRate = $this->employeeLoadPayroll->dailyRate($salary);
            $basicTaxable = $dailyRate !== null
                ? round($dailyRate * $workedDays, 2)
                : 0.0;
            $computedHours = 0.0;
        }

        $hourlyRate = $salary->hourlyRate();
        $lateDeduction = ($lateMinutes > 0 && $hourlyRate !== null)
            ? round(($lateMinutes / 60) * $hourlyRate, 2)
            : 0.0;
        $undertimeDeduction = ($undertimeMinutes > 0 && $hourlyRate !== null)
            ? round(($undertimeMinutes / 60) * $hourlyRate, 2)
            : 0.0;

        return [
            'worked_days' => $workedDays,
            'basic_taxable' => $basicTaxable,
            'basic_non_taxable' => 0.0,
            'late_minutes' => $lateMinutes,
            'late_deduction' => $lateDeduction,
            'undertime_minutes' => $undertimeMinutes,
            'undertime_deduction' => $undertimeDeduction,
            'absent_sessions' => $absentSessions,
            'computed_hours' => round($computedHours, 4),
            'late_days' => $lateDays,
            'undertime_days' => $undertimeDays,
            'ot_offset_minutes' => $otOffsetMinutes,
        ];
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
        $basicTaxable = 0.0;
        $computedHours = 0.0;
        $lateMinutes = 0;
        $lateDeduction = 0.0;
        $undertimeMinutes = 0;
        $undertimeDeduction = 0.0;
        $absentSessions = 0;
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

            if ($schedule['shift'] !== null && (bool) $schedule['shift']->is_flexi_time) {
                $workedDays++;

                continue;
            }

            $dateKey = $session['date']->toDateString();
            $otForDay = (int) ($otMinutesByDate[$dateKey] ?? 0);
            $resolvedLate = $this->resolvedLateForSession($session, $policy, $schedule['start'], $otForDay);
            $resolvedUndertime = $this->resolvedUndertimeForSession($session, $policy, $schedule['end']);

            if (! $resolvedLate['is_absent']) {
                $workedDays++;
                $otOffsetMinutes += (int) ($resolvedLate['ot_offset_minutes'] ?? 0);

                if ($salary->use_basic_income_as_hourly_rate) {
                    if ($resolvedLate['use_full_schedule_hours'] ?? false) {
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
                    $hourlyRate = $salary->hourlyRate();

                    if ($sessionHours !== null && $hourlyRate !== null) {
                        $basicTaxable += $sessionHours * $hourlyRate;
                        $computedHours += $sessionHours;
                    }
                } else {
                    $dailyRate = $this->employeeLoadPayroll->dailyRate($salary);

                    if ($dailyRate !== null) {
                        $basicTaxable += $dailyRate;
                    }
                }

                $lateMinutes += $resolvedLate['billable_minutes'];

                if ($resolvedLate['billable_minutes'] > 0) {
                    $lateDays++;
                }
            } else {
                $absentSessions++;
            }

            $undertimeMinutes += $resolvedUndertime['billable_minutes'];

            if ($resolvedUndertime['billable_minutes'] > 0) {
                $undertimeDays++;
            }

            $hourlyRate = $salary->hourlyRate();

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
            'basic_taxable' => round($basicTaxable, 2),
            'basic_non_taxable' => 0.0,
            'late_minutes' => $lateMinutes,
            'late_deduction' => round($lateDeduction, 2),
            'undertime_minutes' => $undertimeMinutes,
            'undertime_deduction' => round($undertimeDeduction, 2),
            'absent_sessions' => $absentSessions,
            'computed_hours' => round($computedHours, 4),
            'late_days' => $lateDays,
            'undertime_days' => $undertimeDays,
            'ot_offset_minutes' => $otOffsetMinutes,
        ];
    }

    /**
     * Daily work sessions for payroll: first IN and last OUT per calendar day.
     * Middle punches on the same day are interpreted as break logs (see PayrollBreakService).
     *
     * @return Collection<int, array{date: CarbonImmutable, time_in: string|null, time_out: string|null}>
     */
    public function daySessionsForPeriod(
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
    ): Collection {
        return $this->punchesForEmployeeInPeriod($employeeId, $from, $to)
            ->groupBy(fn (RawTimekeepingInandout $punch) => $punch->dt_datetime?->toDateString())
            ->filter(fn ($group, $date) => $date !== null && $date !== '')
            ->map(function (Collection $dayPunches, string $date) {
                $session = $this->breakPayroll->payrollSessionFromPunches($dayPunches);

                return [
                    'date' => CarbonImmutable::parse($date),
                    'time_in' => $session['time_in'],
                    'time_out' => $session['time_out'],
                ];
            })
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
     *     use_full_schedule_hours: bool
     * }
     */
    public function resolvedLateForSession(
        array $session,
        ?TimekeepingPolicy $policy,
        ?string $scheduleStart,
        int $otMinutesForDay = 0,
    ): array {
        $raw = $this->lateRawMinutesForSession($session, $policy, $scheduleStart);
        $resolved = $this->employeeLoadPayroll->resolveLateMinutes($raw, $policy?->timekeeping_policy_id);

        return $this->applyAbsentOtOffset($resolved, $policy, $otMinutesForDay);
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
    ): array {
        $raw = $this->undertimeRawMinutesForSession($session, $scheduleEnd);

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
    public function undertimeRawMinutesForSession(array $session, ?string $scheduleEnd): int
    {
        if ($session['time_out'] === null || $session['time_out'] === '') {
            return 0;
        }

        if ($scheduleEnd === null || trim($scheduleEnd) === '') {
            return 0;
        }

        try {
            $scheduledEnd = $session['date']->setTimeFromTimeString($scheduleEnd);
            $timeOutAt = $session['date']->setTimeFromTimeString($session['time_out']);
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

    /**
     * @return Collection<string, Collection<int, RawTimekeepingInandout>>
     */
    public function dayPunchesForPeriod(
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
    ): Collection {
        return $this->punchesForEmployeeInPeriod($employeeId, $from, $to)
            ->groupBy(fn (RawTimekeepingInandout $punch) => $punch->dt_datetime?->toDateString())
            ->filter(fn ($group, $date) => $date !== null && $date !== '');
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

            $scheduledMinutes = $this->breakPayroll->scheduledBreakMinutes($dayShift);
            $actualMinutes = $this->breakPayroll->actualBreakMinutesFromPunches($dayPunches);

            if ($actualMinutes <= 0) {
                continue;
            }

            $total += $this->breakPayroll->resolvedBreakLateMinutes(
                $policy,
                $actualMinutes,
                $scheduledMinutes,
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
