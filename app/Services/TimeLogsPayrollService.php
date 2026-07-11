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
                $timeInPunch = $dayPunches->first(fn (RawTimekeepingInandout $punch) => (bool) $punch->is_in);
                $timeOutPunch = $dayPunches->last(fn (RawTimekeepingInandout $punch) => ! (bool) $punch->is_in);

                return [
                    'date' => CarbonImmutable::parse($date),
                    'time_in' => $timeInPunch?->dt_datetime?->format('H:i:s'),
                    'time_out' => $timeOutPunch?->dt_datetime?->format('H:i:s'),
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
}
