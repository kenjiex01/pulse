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
        ];

        if (! $this->usesTimeLogs($salary)) {
            return $empty;
        }

        $sessions = $this->daySessionsForPeriod($employeeId, $from, $to);

        if ($sessions->isEmpty()) {
            return $empty;
        }

        $isFlexiShift = $shiftCode !== null && (bool) $shiftCode->is_flexi_time;

        if ($isFlexiShift) {
            $workedDays = $sessions
                ->filter(fn (array $session) => $session['time_in'] !== null && $session['time_in'] !== '')
                ->count();

            return [
                'worked_days' => $workedDays,
                'basic_taxable' => 0.0,
                'basic_non_taxable' => 0.0,
                'late_minutes' => 0,
                'late_deduction' => 0.0,
                'undertime_minutes' => 0,
                'undertime_deduction' => 0.0,
                'absent_sessions' => 0,
            ];
        }

        $workedDays = $this->countWorkedDays($sessions, $policy, $scheduleStart);
        $dailyRate = $this->employeeLoadPayroll->dailyRate($salary);
        $basicTaxable = $dailyRate !== null
            ? round($dailyRate * $workedDays, 2)
            : 0.0;

        $lateMinutes = $this->totalLateMinutes($sessions, $policy, $scheduleStart);
        $undertimeMinutes = $this->totalUndertimeMinutes($sessions, $policy, $scheduleEnd);
        $absentSessions = $this->countAbsentSessions($sessions, $policy, $scheduleStart);
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
     *     absent_sessions: int
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
    ): array {
        if ($salaries->isEmpty()) {
            return $this->computeForPeriod(new EmployeeSalary, $employeeId, $from, $to, $policy, $scheduleStart, $scheduleEnd, $shiftCode);
        }

        if ($salaries->count() === 1) {
            return $this->computeForPeriod($salaries->first(), $employeeId, $from, $to, $policy, $scheduleStart, $scheduleEnd, $shiftCode);
        }

        $primary = $salaries->last();

        if ((int) $primary->basic_computation_id !== BasicComputation::TIME_IN_OUT) {
            return $this->computeForPeriod($primary, $employeeId, $from, $to, $policy, $scheduleStart, $scheduleEnd, $shiftCode);
        }

        $sessions = $this->daySessionsForPeriod($employeeId, $from, $to);

        if ($sessions->isEmpty()) {
            return $this->computeForPeriod($primary, $employeeId, $from, $to, $policy, $scheduleStart, $scheduleEnd, $shiftCode);
        }

        if ($shiftCode !== null && (bool) $shiftCode->is_flexi_time) {
            return $this->computeForPeriod($primary, $employeeId, $from, $to, $policy, $scheduleStart, $scheduleEnd, $shiftCode);
        }

        $workedDays = 0;
        $basicTaxable = 0.0;
        $lateMinutes = 0;
        $lateDeduction = 0.0;
        $undertimeMinutes = 0;
        $undertimeDeduction = 0.0;
        $absentSessions = 0;

        foreach ($sessions as $session) {
            $salary = $resolver->salaryEffectiveOnDate($salaries, $session['date']);

            if ($salary === null) {
                continue;
            }

            $resolvedLate = $this->resolvedLateForSession($session, $policy, $scheduleStart);
            $resolvedUndertime = $this->resolvedUndertimeForSession($session, $policy, $scheduleEnd);

            if ($session['time_in'] !== null && $session['time_in'] !== '') {
                if (! $resolvedLate['is_absent']) {
                    $workedDays++;
                    $dailyRate = $this->employeeLoadPayroll->dailyRate($salary);

                    if ($dailyRate !== null) {
                        $basicTaxable += $dailyRate;
                    }

                    $lateMinutes += $resolvedLate['billable_minutes'];
                } else {
                    $absentSessions++;
                }
            }

            $undertimeMinutes += $resolvedUndertime['billable_minutes'];
            $hourlyRate = $salary->hourlyRate();

            if ($hourlyRate === null) {
                continue;
            }

            if (! $resolvedLate['is_absent']) {
                $lateDeduction += ($resolvedLate['billable_minutes'] / 60) * $hourlyRate;
            }

            $undertimeDeduction += ($resolvedUndertime['billable_minutes'] / 60) * $hourlyRate;
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
     * @param  array{date: CarbonImmutable, time_in: string|null, time_out: string|null}  $session
     * @return array{
     *     raw_minutes: int,
     *     equivalent_minutes: int|null,
     *     is_absent: bool,
     *     billable_minutes: int
     * }
     */
    public function resolvedLateForSession(
        array $session,
        ?TimekeepingPolicy $policy,
        ?string $scheduleStart,
    ): array {
        $raw = $this->lateRawMinutesForSession($session, $policy, $scheduleStart);

        return $this->employeeLoadPayroll->resolveLateMinutes($raw, $policy?->timekeeping_policy_id);
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

        $scheduledMinutes = $this->breakPayroll->scheduledBreakMinutes($shiftCode);
        $total = 0;

        foreach ($this->dayPunchesForPeriod($employeeId, $from, $to) as $dayPunches) {
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
}
