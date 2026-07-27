<?php

namespace App\Services;

use App\Models\EmployeeSalary;
use App\Models\RawEmployeeLoadEntry;
use App\Models\TimekeepingPolicy;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PayrollOvertimeService
{
    public function __construct(
        private readonly EmployeeLoadPayrollService $employeeLoadPayroll,
    ) {}

    public function considersOvertime(?TimekeepingPolicy $policy): bool
    {
        return TimekeepingPolicySupport::considersExcessAsOvertime($policy);
    }

    /**
     * @param  Collection<int, array{date: CarbonImmutable, time_in: string|null, time_out: string|null}>  $sessions
     */
    public function totalBillableMinutes(
        Collection $sessions,
        ?TimekeepingPolicy $policy,
        ?string $scheduleStart,
        ?string $scheduleEnd,
    ): int {
        if (! $this->considersOvertime($policy)) {
            return 0;
        }

        $total = 0;

        foreach ($sessions as $session) {
            $total += $this->billableMinutesForSession(
                $session['date'],
                $session['time_in'] ?? null,
                $session['time_out'] ?? null,
                $scheduleStart,
                $scheduleEnd,
                $policy,
            );
        }

        return TimekeepingPolicySupport::applyRoundingMinutes(
            $total,
            $policy?->overtime_rounding_id,
        );
    }

    /**
     * @param  Collection<int, RawEmployeeLoadEntry>  $entries
     */
    public function totalBillableMinutesFromEntries(
        Collection $entries,
        ?TimekeepingPolicy $policy,
    ): int {
        if (! $this->considersOvertime($policy)) {
            return 0;
        }

        $total = 0;

        foreach ($entries as $entry) {
            if (! $entry instanceof RawEmployeeLoadEntry || $entry->session_date === null) {
                continue;
            }

            $sessionDate = CarbonImmutable::parse($entry->session_date->toDateString());
            $total += $this->billableMinutesForSession(
                $sessionDate,
                $entry->time_in,
                $entry->time_out,
                $this->employeeLoadPayroll->parseScheduleStart($entry->class_schedule),
                $this->employeeLoadPayroll->parseScheduleEnd($entry->class_schedule),
                $policy,
            );
        }

        return TimekeepingPolicySupport::applyRoundingMinutes(
            $total,
            $policy?->overtime_rounding_id,
        );
    }

    public function overtimePay(EmployeeSalary $salary, int $billableMinutes): float
    {
        if ($billableMinutes <= 0) {
            return 0.0;
        }

        $hourlyRate = $salary->hourlyRate();

        if ($hourlyRate === null) {
            return 0.0;
        }

        return round(($billableMinutes / 60) * $hourlyRate, 2);
    }

    /**
     * @return array{regular_minutes: int, special_minutes: int}
     */
    public function billableMinutesBreakdownForSession(
        CarbonImmutable $sessionDate,
        ?string $timeIn,
        ?string $timeOut,
        ?string $scheduleStart,
        ?string $scheduleEnd,
        ?TimekeepingPolicy $policy,
    ): array {
        if ($policy === null || ! $this->considersOvertime($policy)) {
            return ['regular_minutes' => 0, 'special_minutes' => 0];
        }

        $minMinutes = (int) round((float) ($policy->min_minutes ?? 0));
        $beforeMinutes = 0;

        if ($policy->is_consider_before_time) {
            $beforeMinutes = $this->applyMinimumMinutes(
                $this->rawBeforeMinutes($sessionDate, $timeIn, $scheduleStart),
                $minMinutes,
            );
        }

        $afterBreakdown = $this->rawAfterBillableMinutesBreakdown(
            $sessionDate,
            $timeOut,
            $scheduleEnd,
            $policy,
        );

        $regularMinutes = $beforeMinutes + $afterBreakdown['regular_minutes'];
        $specialMinutes = $afterBreakdown['special_minutes'];

        $regularMinutes = TimekeepingPolicySupport::resolveOvertimeEquivalent(
            $policy->timekeeping_policy_id,
            $regularMinutes,
        )['billable_minutes'];

        $specialMinutes = TimekeepingPolicySupport::resolveOvertimeEquivalent(
            $policy->timekeeping_policy_id,
            $specialMinutes,
        )['billable_minutes'];

        $regularMinutes = TimekeepingPolicySupport::applyRoundingMinutes(
            $regularMinutes,
            $policy->overtime_rounding_id,
        );
        $specialMinutes = TimekeepingPolicySupport::applyRoundingMinutes(
            $specialMinutes,
            $policy->overtime_rounding_id,
        );

        return [
            'regular_minutes' => $regularMinutes,
            'special_minutes' => $specialMinutes,
        ];
    }

    private function billableMinutesForSession(
        CarbonImmutable $sessionDate,
        ?string $timeIn,
        ?string $timeOut,
        ?string $scheduleStart,
        ?string $scheduleEnd,
        ?TimekeepingPolicy $policy,
    ): int {
        if ($policy === null || ! $this->considersOvertime($policy)) {
            return 0;
        }

        $minMinutes = (int) round((float) ($policy->min_minutes ?? 0));
        $rawMinutes = 0;

        if ($policy->is_consider_before_time) {
            $rawMinutes += $this->applyMinimumMinutes(
                $this->rawBeforeMinutes($sessionDate, $timeIn, $scheduleStart),
                $minMinutes,
            );
        }

        if ($policy->is_consider_after_time) {
            $afterBreakdown = $this->rawAfterBillableMinutesBreakdown(
                $sessionDate,
                $timeOut,
                $scheduleEnd,
                $policy,
            );
            $rawMinutes += $afterBreakdown['regular_minutes'] + $afterBreakdown['special_minutes'];
        }

        return TimekeepingPolicySupport::resolveOvertimeEquivalent(
            $policy->timekeeping_policy_id,
            $rawMinutes,
        )['billable_minutes'];
    }

    /**
     * @return array{regular_minutes: int, special_minutes: int}
     */
    private function rawAfterBillableMinutesBreakdown(
        CarbonImmutable $sessionDate,
        ?string $timeOut,
        ?string $scheduleEnd,
        TimekeepingPolicy $policy,
    ): array {
        if ($timeOut === null || $timeOut === '' || $scheduleEnd === null || trim($scheduleEnd) === '') {
            return ['regular_minutes' => 0, 'special_minutes' => 0];
        }

        try {
            $scheduledEnd = $sessionDate->setTimeFromTimeString($scheduleEnd);
            $timeOutAt = $sessionDate->setTimeFromTimeString($timeOut);
        } catch (\Throwable) {
            return ['regular_minutes' => 0, 'special_minutes' => 0];
        }

        if ($timeOutAt->lessThanOrEqualTo($scheduledEnd)) {
            return ['regular_minutes' => 0, 'special_minutes' => 0];
        }

        $minMinutes = (int) round((float) ($policy->min_minutes ?? 0));
        $specialStart = trim((string) ($policy->special_ot_start ?? ''));

        if ($specialStart === '') {
            return [
                'regular_minutes' => $this->applyMinimumMinutes(
                    (int) $scheduledEnd->diffInMinutes($timeOutAt),
                    $minMinutes,
                ),
                'special_minutes' => 0,
            ];
        }

        try {
            $specialAt = $sessionDate->setTimeFromTimeString($specialStart);
        } catch (\Throwable) {
            return [
                'regular_minutes' => $this->applyMinimumMinutes(
                    (int) $scheduledEnd->diffInMinutes($timeOutAt),
                    $minMinutes,
                ),
                'special_minutes' => 0,
            ];
        }

        $regularMinutes = 0;
        $specialMinutes = 0;

        if ($timeOutAt->greaterThan($scheduledEnd)) {
            $regularEnd = $timeOutAt->lessThan($specialAt) ? $timeOutAt : $specialAt;

            if ($regularEnd->greaterThan($scheduledEnd)) {
                $regularMinutes = (int) $scheduledEnd->diffInMinutes($regularEnd);
            }
        }

        if ($timeOutAt->greaterThan($specialAt)) {
            $specialMinutes = (int) $specialAt->diffInMinutes($timeOutAt);
        }

        $specialMin = (int) round((float) ($policy->special_ot_min_minutes ?? 0));

        return [
            'regular_minutes' => $this->applyMinimumMinutes($regularMinutes, $minMinutes),
            'special_minutes' => $this->applyMinimumMinutes($specialMinutes, $specialMin),
        ];
    }

    private function applyMinimumMinutes(int $minutes, int $minimumMinutes): int
    {
        if ($minutes <= 0) {
            return 0;
        }

        if ($minimumMinutes > 0 && $minutes < $minimumMinutes) {
            return 0;
        }

        return $minutes;
    }

    private function rawBeforeMinutes(
        CarbonImmutable $sessionDate,
        ?string $timeIn,
        ?string $scheduleStart,
    ): int {
        if ($timeIn === null || $timeIn === '' || $scheduleStart === null || trim($scheduleStart) === '') {
            return 0;
        }

        try {
            $scheduledAt = $sessionDate->setTimeFromTimeString($scheduleStart);
            $timeInAt = $sessionDate->setTimeFromTimeString($timeIn);
        } catch (\Throwable) {
            return 0;
        }

        if ($timeInAt->greaterThanOrEqualTo($scheduledAt)) {
            return 0;
        }

        return (int) $timeInAt->diffInMinutes($scheduledAt);
    }

    private function rawAfterBillableMinutes(
        CarbonImmutable $sessionDate,
        ?string $timeOut,
        ?string $scheduleEnd,
        TimekeepingPolicy $policy,
    ): int {
        $breakdown = $this->rawAfterBillableMinutesBreakdown($sessionDate, $timeOut, $scheduleEnd, $policy);

        return $breakdown['regular_minutes'] + $breakdown['special_minutes'];
    }
}
