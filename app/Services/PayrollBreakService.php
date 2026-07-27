<?php

namespace App\Services;

use App\Models\RawTimekeepingInandout;
use App\Models\ShiftCode;
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

        return (int) $shiftCode->breaks->sum('shift_code_break_minute');
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
     * Payroll work window per day: chronologically first IN and last OUT.
     * Punches between them (OUT → IN pairs) are break logs — see breakSegmentsFromPunches().
     *
     * @param  Collection<int, RawTimekeepingInandout>  $dayPunches
     * @return array{time_in: string|null, time_out: string|null}
     */
    public function payrollSessionFromPunches(Collection $dayPunches): array
    {
        $ordered = $this->orderedPunches($dayPunches);

        $timeInPunch = $ordered->first(fn (RawTimekeepingInandout $punch) => (bool) $punch->is_in);
        $timeOutPunch = $ordered->last(fn (RawTimekeepingInandout $punch) => ! (bool) $punch->is_in);

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

        $lastOutIndex = null;

        for ($index = $ordered->count() - 1; $index >= 0; $index--) {
            if (! (bool) $ordered[$index]->is_in) {
                $lastOutIndex = $index;
                break;
            }
        }

        if ($firstInIndex === false || $lastOutIndex === null || $lastOutIndex <= $firstInIndex) {
            return [];
        }

        $segments = [];

        for ($index = $firstInIndex + 1; $index < $lastOutIndex; $index++) {
            $current = $ordered[$index];
            $next = $ordered[$index + 1] ?? null;

            if ($next === null) {
                break;
            }

            if ((bool) $current->is_in || ! (bool) $next->is_in) {
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
