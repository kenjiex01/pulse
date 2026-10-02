<?php

namespace App\Services;

use App\Models\RawTimekeepingInandout;
use App\Models\ShiftCode;
use App\Models\ShiftCodeBreak;
use App\Models\TimekeepingPolicy;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PayrollBreakService
{
    public function deductsBreakTardiness(?TimekeepingPolicy $policy): bool
    {
        return $policy !== null && (bool) $policy->break_deduct_tardiness;
    }

    public function scheduledBreakMinutes(?ShiftCode $shiftCode): int
    {
        if ($shiftCode === null) {
            return 0;
        }

        $shiftCode->loadMissing('breaks');

        return (int) $shiftCode->breaks->sum(function ($break) {
            if (filled($break->break_out) && filled($break->break_in)) {
                try {
                    $start = \Carbon\CarbonImmutable::parse('2000-01-01 '.$break->break_out);
                    $end = \Carbon\CarbonImmutable::parse('2000-01-01 '.$break->break_in);

                    if ($end->lessThanOrEqualTo($start)) {
                        $end = $end->addDay();
                    }

                    return (int) $start->diffInMinutes($end);
                } catch (\Throwable) {
                    // fall through to stored minutes
                }
            }

            return (int) $break->shift_code_break_minute;
        });
    }

    public function actualBreakMinutesFromPunches(Collection $dayPunches): int
    {
        return $this->consumedBreakMinutesFromPunches($dayPunches);
    }

    /**
     * Allowed break minutes before break tardiness applies (shift schedule + optional grace).
     */
    public function allowedBreakMinutes(?TimekeepingPolicy $policy, int $scheduledMinutes): int
    {
        $allowedMinutes = max(0, $scheduledMinutes);

        if (
            $policy !== null
            && $policy->is_break_deduct_grace_period
            && (float) ($policy->break_grace_period ?? 0) > 0
        ) {
            $allowedMinutes += (int) round((float) $policy->break_grace_period);
        }

        return $allowedMinutes;
    }

    /**
     * @param  Collection<int, RawTimekeepingInandout>  $dayPunches
     */
    public function consumedBreakMinutesFromPunches(Collection $dayPunches): int
    {
        $total = 0;

        foreach ($this->breakSegmentsFromPunches($dayPunches) as $segment) {
            $total += $segment['minutes'];
        }

        return $total;
    }

    /**
     * Payroll work window per day: chronologically first IN and last punch as OUT.
     *
     * Employees often punch multiple times (duplicate Ins, mid-day Out/In for break).
     * Session start = first In. Session end = last punch of the day — even when that
     * last punch was mistakenly tagged as In (missing Out). Punches between them
     * (OUT → IN pairs) are break logs — see breakSegmentsFromPunches().
     *
     * @param  Collection<int, RawTimekeepingInandout>  $dayPunches
     * @return array{time_in: string|null, time_out: string|null}
     */
    public function payrollSessionFromPunches(Collection $dayPunches): array
    {
        $ordered = $this->orderedPunches($dayPunches);

        $timeInPunch = $ordered->first(fn (RawTimekeepingInandout $punch) => (bool) $punch->is_in);
        $lastPunch = $ordered->last();

        $timeOutPunch = null;

        if ($lastPunch !== null) {
            // Prefer a true Out when it is the last punch; otherwise treat the day's
            // last log as Time Out (covers dangling In at end of day).
            if (! (bool) $lastPunch->is_in) {
                $timeOutPunch = $lastPunch;
            } elseif ($timeInPunch !== null
                && (int) $lastPunch->timekeeping_inandout_id !== (int) $timeInPunch->timekeeping_inandout_id) {
                $timeOutPunch = $lastPunch;
            } else {
                // Only one In and no Out — no complete session end.
                $timeOutPunch = $ordered->last(fn (RawTimekeepingInandout $punch) => ! (bool) $punch->is_in);
            }
        }

        return [
            'time_in' => $timeInPunch?->dt_datetime?->format('H:i:s'),
            'time_out' => $timeOutPunch?->dt_datetime?->format('H:i:s'),
        ];
    }

    /**
     * @param  Collection<int, RawTimekeepingInandout>  $dayPunches
     * @return list<array{break_out: CarbonImmutable, break_in: CarbonImmutable, minutes: int}>
     */
    public function breakSegmentsFromPunches(Collection $dayPunches): array
    {
        if ($dayPunches->count() < 3) {
            return [];
        }

        $ordered = $this->orderedPunches($dayPunches);

        if ($ordered->count() < 3) {
            return [];
        }

        $firstInIndex = $ordered->search(fn (RawTimekeepingInandout $punch) => (bool) $punch->is_in);
        $session = $this->payrollSessionFromPunches($dayPunches);
        $sessionEnd = $session['time_out'];

        if ($firstInIndex === false || $sessionEnd === null || $sessionEnd === '') {
            return [];
        }

        $lastEndIndex = null;

        for ($index = $ordered->count() - 1; $index >= 0; $index--) {
            $at = $ordered[$index]->dt_datetime?->format('H:i:s');
            if ($at === $sessionEnd) {
                $lastEndIndex = $index;
                break;
            }
        }

        if ($lastEndIndex === null || $lastEndIndex <= $firstInIndex) {
            return [];
        }

        $lastPunchIsSyntheticOut = (bool) $ordered[$lastEndIndex]->is_in;
        $segments = [];

        for ($index = $firstInIndex + 1; $index < $lastEndIndex; $index++) {
            $current = $ordered[$index];
            $next = $ordered[$index + 1] ?? null;

            if ($next === null) {
                break;
            }

            if ((bool) $current->is_in || ! (bool) $next->is_in) {
                continue;
            }

            // When the day's last punch is an In treated as Out, do not count
            // Out → that final In as a break (it is the end of the work window).
            if ($lastPunchIsSyntheticOut && ($index + 1) === $lastEndIndex) {
                continue;
            }

            $breakOut = CarbonImmutable::parse($current->dt_datetime);
            $breakIn = CarbonImmutable::parse($next->dt_datetime);

            if ($breakIn->lessThanOrEqualTo($breakOut)) {
                continue;
            }

            $segments[] = [
                'break_out' => $breakOut,
                'break_in' => $breakIn,
                'minutes' => (int) $breakOut->diffInMinutes($breakIn),
            ];
        }

        return $segments;
    }

    /**
     * @param  Collection<int, RawTimekeepingInandout>  $dayPunches
     * @return Collection<int, RawTimekeepingInandout>
     */
    private function orderedPunches(Collection $dayPunches): Collection
    {
        return $dayPunches
            ->filter(fn (RawTimekeepingInandout $punch) => $punch->dt_datetime !== null)
            ->sortBy([
                ['dt_datetime', 'asc'],
                ['timekeeping_inandout_id', 'asc'],
            ])
            ->values();
    }

    public function consumedBreakMinutes(
        ?TimekeepingPolicy $policy,
        int $scheduledMinutes,
        int $actualMinutes,
    ): int {
        $usesScheduled = (int) ($policy?->break_computation ?? TimekeepingPolicySupport::BREAK_COMPUTATION_SCHEDULED)
            === TimekeepingPolicySupport::BREAK_COMPUTATION_SCHEDULED;

        if ($usesScheduled) {
            return max(0, $scheduledMinutes);
        }

        if ($actualMinutes > 0) {
            return $actualMinutes;
        }

        return 0;
    }

    /**
     * @return list<array{break_out: ?string, break_in: ?string, minutes: int}>
     */
    public function scheduledShiftBreakDefinitions(?ShiftCode $shiftCode): array
    {
        if ($shiftCode === null) {
            return [];
        }

        $shiftCode->loadMissing('breaks');

        return $shiftCode->breaks
            ->sortBy('shift_code_break_no')
            ->values()
            ->map(function (ShiftCodeBreak $break) {
                $minutes = (int) $break->shift_code_break_minute;

                if ($minutes <= 0 && filled($break->break_out) && filled($break->break_in)) {
                    try {
                        $start = CarbonImmutable::parse('2000-01-01 '.$break->break_out);
                        $end = CarbonImmutable::parse('2000-01-01 '.$break->break_in);

                        if ($end->lessThanOrEqualTo($start)) {
                            $end = $end->addDay();
                        }

                        $minutes = (int) $start->diffInMinutes($end);
                    } catch (\Throwable) {
                        $minutes = 0;
                    }
                }

                return [
                    'break_out' => filled($break->break_out) ? trim((string) $break->break_out) : null,
                    'break_in' => filled($break->break_in) ? trim((string) $break->break_in) : null,
                    'minutes' => max(0, $minutes),
                ];
            })
            ->all();
    }

    /**
     * Break late before policy brackets (caller must gate on {@see deductsBreakTardiness()}).
     *
     * - Always: actual break length vs shift **Break Minute** (+ break grace).
     * - Additionally, when shift has **both** Break Out and Break In: late leave/return vs that window.
     *
     * @param  Collection<int, RawTimekeepingInandout>  $dayPunches
     */
    public function rawBreakLateMinutesForDay(
        CarbonImmutable $sessionDate,
        Collection $dayPunches,
        ?ShiftCode $shiftCode,
        ?TimekeepingPolicy $policy,
    ): int {
        $segments = $this->breakSegmentsFromPunches($dayPunches);

        if ($segments === []) {
            return 0;
        }

        $scheduledMinutes = $this->scheduledBreakMinutes($shiftCode);
        $allowedMinutes = $this->allowedBreakMinutes($policy, $scheduledMinutes);
        $consumedMinutes = $this->consumedBreakMinutesFromPunches($dayPunches);
        $durationExcess = max(0, $consumedMinutes - $allowedMinutes);

        $shiftBreaks = $this->scheduledShiftBreakDefinitions($shiftCode);
        $windowLate = 0;

        foreach ($segments as $index => $segment) {
            $breakDef = $shiftBreaks[$index] ?? ($shiftBreaks[0] ?? null);

            if (! $this->shiftBreakHasScheduleWindow($breakDef)) {
                continue;
            }

            $windowLate += $this->windowBreakLateMinutesForSegment($sessionDate, $segment, $breakDef);
        }

        return max($durationExcess, $windowLate);
    }

    /**
     * @param  array{break_out: ?string, break_in: ?string, minutes: int}|null  $shiftBreak
     */
    private function shiftBreakHasScheduleWindow(?array $shiftBreak): bool
    {
        return $shiftBreak !== null
            && filled($shiftBreak['break_out'] ?? null)
            && filled($shiftBreak['break_in'] ?? null);
    }

    /**
     * @param  array{break_out: CarbonImmutable, break_in: CarbonImmutable, minutes: int}  $segment
     * @param  array{break_out: ?string, break_in: ?string, minutes: int}|null  $shiftBreak
     */
    private function windowBreakLateMinutesForSegment(
        CarbonImmutable $sessionDate,
        array $segment,
        ?array $shiftBreak,
    ): int {
        if ($shiftBreak === null || $shiftBreak['break_out'] === null || $shiftBreak['break_in'] === null) {
            return 0;
        }

        try {
            $scheduledOut = $this->clockOnDate($sessionDate, $shiftBreak['break_out']);
            $scheduledIn = $this->clockOnDate($sessionDate, $shiftBreak['break_in']);

            if ($scheduledIn->lessThanOrEqualTo($scheduledOut)) {
                $scheduledIn = $scheduledIn->addDay();
            }
        } catch (\Throwable) {
            return 0;
        }

        $actualOut = $segment['break_out'];
        $actualIn = $segment['break_in'];

        $lateDepart = 0;
        if ($actualOut->greaterThan($scheduledOut)) {
            $lateDepart = (int) $scheduledOut->diffInMinutes($actualOut);
        }

        $lateReturn = 0;
        if ($actualIn->greaterThan($scheduledIn)) {
            $lateReturn = (int) $scheduledIn->diffInMinutes($actualIn);
        }

        return max($lateDepart, $lateReturn);
    }

    private function clockOnDate(CarbonImmutable $sessionDate, string $time): CarbonImmutable
    {
        $normalized = strlen(trim($time)) <= 5
            ? trim($time).':00'
            : trim($time);

        return $sessionDate->setTimeFromTimeString($normalized);
    }

    /**
     * @param  Collection<int, RawTimekeepingInandout>  $dayPunches
     * @return array{
     *     raw_minutes: int,
     *     equivalent_minutes: int|null,
     *     billable_minutes: int
     * }
     */
    public function resolvedBreakLateMinutesForDay(
        ?TimekeepingPolicy $policy,
        Collection $dayPunches,
        ?ShiftCode $shiftCode,
        CarbonImmutable $sessionDate,
    ): array {
        $empty = [
            'raw_minutes' => 0,
            'equivalent_minutes' => null,
            'billable_minutes' => 0,
        ];

        if ($policy === null || ! $this->deductsBreakTardiness($policy)) {
            return $empty;
        }

        $rawLate = $this->rawBreakLateMinutesForDay($sessionDate, $dayPunches, $shiftCode, $policy);

        if ($rawLate <= 0) {
            return $empty;
        }

        return $this->applyBreakLatePolicy($policy, $rawLate);
    }

    /**
     * @return array{
     *     raw_minutes: int,
     *     equivalent_minutes: int|null,
     *     billable_minutes: int
     * }
     */
    public function resolvedBreakLateMinutes(
        ?TimekeepingPolicy $policy,
        int $consumedMinutes,
        int $scheduledMinutes,
    ): array {
        $empty = [
            'raw_minutes' => 0,
            'equivalent_minutes' => null,
            'billable_minutes' => 0,
        ];

        if ($policy === null || ! $this->deductsBreakTardiness($policy) || $consumedMinutes <= 0) {
            return $empty;
        }

        $allowedMinutes = $this->allowedBreakMinutes($policy, $scheduledMinutes);

        if ($consumedMinutes <= $allowedMinutes) {
            return $empty;
        }

        $rawLate = $consumedMinutes - $allowedMinutes;

        return $this->applyBreakLatePolicy($policy, $rawLate);
    }

    /**
     * @return array{
     *     raw_minutes: int,
     *     equivalent_minutes: int|null,
     *     billable_minutes: int
     * }
     */
    private function applyBreakLatePolicy(?TimekeepingPolicy $policy, int $rawLate): array
    {
        $resolved = TimekeepingPolicySupport::resolveBreakTardinessEquivalent(
            $policy->timekeeping_policy_id,
            $rawLate,
        );

        $billable = TimekeepingPolicySupport::applyBreakTardinessRoundingMinutes(
            $resolved['billable_minutes'],
            $policy->break_tardiness_rounding_id,
        );

        return [
            'raw_minutes' => $rawLate,
            'equivalent_minutes' => $resolved['equivalent_minutes'],
            'billable_minutes' => $billable,
        ];
    }
}
