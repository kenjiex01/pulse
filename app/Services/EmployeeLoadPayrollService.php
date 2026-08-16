<?php

namespace App\Services;

use App\Models\BasicComputation;
use App\Models\DeductionType;
use App\Models\EmployeeSalary;
use App\Models\PayType;
use App\Models\RawEmployeeLoadEntry;
use App\Models\TimekeepingPolicy;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EmployeeLoadPayrollService
{
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
        ?string $employeeNumber,
        CarbonInterface $from,
        CarbonInterface $to,
        ?TimekeepingPolicy $policy = null,
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
            'late_days' => 0,
            'undertime_days' => 0,
        ];

        if ((int) $salary->basic_computation_id !== BasicComputation::TIME_IN_OUT) {
            return $empty;
        }

        $entries = $this->entriesForEmployeeInPeriod($employeeId, $employeeNumber, $from, $to);

        if ($entries->isEmpty()) {
            return $empty;
        }

        $workedDays = $this->countWorkedDays($entries, $policy);
        $dailyRate = $this->dailyRate($salary);
        $basicTaxable = $dailyRate !== null
            ? round($dailyRate * $workedDays, 2)
            : 0.0;

        $lateMinutes = $this->totalLateMinutes($entries, $policy);
        $undertimeMinutes = $this->totalUndertimeMinutes($entries, $policy);
        $lateDays = $this->countLateDays($entries, $policy);
        $undertimeDays = $this->countUndertimeDays($entries, $policy);
        $absentSessions = $this->countAbsentSessions($entries, $policy);
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
            'late_days' => $lateDays,
            'undertime_days' => $undertimeDays,
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
        ?string $employeeNumber,
        CarbonInterface $from,
        CarbonInterface $to,
        ?TimekeepingPolicy $policy = null,
    ): array {
        if ($salaries->isEmpty()) {
            return $this->computeForPeriod(new EmployeeSalary, $employeeId, $employeeNumber, $from, $to, $policy);
        }

        if ($salaries->count() === 1) {
            return $this->computeForPeriod($salaries->first(), $employeeId, $employeeNumber, $from, $to, $policy);
        }

        $primary = $salaries->last();

        if ((int) $primary->basic_computation_id !== BasicComputation::TIME_IN_OUT) {
            return $this->computeForPeriod($primary, $employeeId, $employeeNumber, $from, $to, $policy);
        }

        $entries = $this->entriesForEmployeeInPeriod($employeeId, $employeeNumber, $from, $to);

        if ($entries->isEmpty()) {
            return $this->computeForPeriod($primary, $employeeId, $employeeNumber, $from, $to, $policy);
        }

        $workedDays = 0;
        $basicTaxable = 0.0;
        $lateMinutes = 0;
        $lateDeduction = 0.0;
        $undertimeMinutes = 0;
        $undertimeDeduction = 0.0;
        $absentSessions = 0;
        $lateDays = 0;
        $undertimeDays = 0;

        foreach ($entries->groupBy(fn (RawEmployeeLoadEntry $entry) => $entry->session_date?->toDateString()) as $date => $dayEntries) {
            if ($date === null || $date === '') {
                continue;
            }

            $salary = $resolver->salaryEffectiveOnDate($salaries, CarbonImmutable::parse($date));

            if ($salary === null) {
                continue;
            }

            $workedThisDay = false;
            $lateThisDay = false;
            $undertimeThisDay = false;

            foreach ($dayEntries as $entry) {
                $resolvedLate = $this->resolvedLateForEntry($entry, $policy);

                if ($resolvedLate['is_absent']) {
                    $absentSessions++;
                } elseif ($entry->time_in !== null && $entry->time_in !== '') {
                    $workedThisDay = true;
                    $lateMinutes += $resolvedLate['billable_minutes'];

                    if ($resolvedLate['billable_minutes'] > 0) {
                        $lateThisDay = true;
                    }
                }

                $undertimeBillable = $this->resolvedUndertimeForEntry($entry, $policy)['billable_minutes'];
                $undertimeMinutes += $undertimeBillable;

                if ($undertimeBillable > 0) {
                    $undertimeThisDay = true;
                }
            }

            if ($lateThisDay) {
                $lateDays++;
            }

            if ($undertimeThisDay) {
                $undertimeDays++;
            }

            if ($workedThisDay) {
                $dailyRate = $this->dailyRate($salary);

                if ($dailyRate !== null) {
                    $basicTaxable += $dailyRate;
                    $workedDays++;
                }
            }

            $hourlyRate = $salary->hourlyRate();

            if ($hourlyRate === null) {
                continue;
            }

            foreach ($dayEntries as $entry) {
                $resolvedLate = $this->resolvedLateForEntry($entry, $policy);

                if (! $resolvedLate['is_absent']) {
                    $lateDeduction += ($resolvedLate['billable_minutes'] / 60) * $hourlyRate;
                }

                $undertimeDeduction += ($this->resolvedUndertimeForEntry($entry, $policy)['billable_minutes'] / 60) * $hourlyRate;
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
            'late_days' => $lateDays,
            'undertime_days' => $undertimeDays,
        ];
    }

    public function usesEmployeeLoad(EmployeeSalary $salary): bool
    {
        return (int) $salary->basic_computation_id === BasicComputation::TIME_IN_OUT;
    }

    /**
     * @return Collection<int, RawEmployeeLoadEntry>
     */
    public function entriesForEmployeeInPeriod(
        int $employeeId,
        ?string $employeeNumber,
        CarbonInterface $from,
        CarbonInterface $to,
    ): Collection {
        return $this->allEntriesForEmployeeInPeriod($employeeId, $employeeNumber, $from, $to)
            ->filter(fn (RawEmployeeLoadEntry $entry) => $entry->time_in !== null && $entry->time_in !== '')
            ->values();
    }

    /**
     * All load rows in period (including rows without matched Time In yet).
     *
     * @return Collection<int, RawEmployeeLoadEntry>
     */
    public function allEntriesForEmployeeInPeriod(
        int $employeeId,
        ?string $employeeNumber,
        CarbonInterface $from,
        CarbonInterface $to,
    ): Collection {
        return RawEmployeeLoadEntry::query()
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->where(function ($query) use ($employeeId, $employeeNumber) {
                $query->where('employee_id', $employeeId);

                if ($employeeNumber !== null && $employeeNumber !== '') {
                    $query->orWhere(function ($fallback) use ($employeeNumber) {
                        $fallback
                            ->whereNull('employee_id')
                            ->where('employee_number', $employeeNumber);
                    });
                }
            })
            ->orderBy('session_date')
            ->orderBy('employee_load_entry_id')
            ->get();
    }

    /**
     * Prefer Time In → Time Out. When late is waived, credit back the late gap
     * so late does not reduce class hours. Fall back to total_hours / schedule.
     */
    public function hoursForEntry(RawEmployeeLoadEntry $entry): float
    {
        $punchHours = $this->punchHoursForEntry($entry);

        if ($punchHours !== null) {
            if ($entry->late_waived) {
                $lateGapHours = $this->clockLateMinutesForEntry($entry) / 60;

                return round(max(0.0, $punchHours + $lateGapHours), 4);
            }

            return $punchHours;
        }

        if ($entry->total_hours !== null && (float) $entry->total_hours > 0) {
            return round((float) $entry->total_hours, 4);
        }

        return $this->scheduleHoursForEntry($entry);
    }

    /**
     * Hours from Time In to Time Out, or null when either punch is missing.
     */
    public function punchHoursForEntry(RawEmployeeLoadEntry $entry): ?float
    {
        if (
            $entry->session_date === null
            || $entry->time_in === null || $entry->time_in === ''
            || $entry->time_out === null || $entry->time_out === ''
        ) {
            return null;
        }

        try {
            $sessionDate = CarbonImmutable::parse($entry->session_date->toDateString());
            $inAt = $sessionDate->setTimeFromTimeString($entry->time_in);
            $outAt = $sessionDate->setTimeFromTimeString($entry->time_out);

            if ($outAt->lessThanOrEqualTo($inAt)) {
                $outAt = $outAt->addDay();
            }

            return max(0.0, round(((int) $inAt->diffInMinutes($outAt)) / 60, 4));
        } catch (\Throwable) {
            return null;
        }
    }

    public function scheduleHoursForEntry(RawEmployeeLoadEntry $entry): float
    {
        $start = $this->parseScheduleStart($entry->class_schedule);
        $end = $this->parseScheduleEnd($entry->class_schedule);

        if ($start === null || $end === null || $entry->session_date === null) {
            return 0.0;
        }

        try {
            $sessionDate = CarbonImmutable::parse($entry->session_date->toDateString());
            $startAt = $sessionDate->setTimeFromTimeString($start);
            $endAt = $sessionDate->setTimeFromTimeString($end);

            if ($endAt->lessThanOrEqualTo($startAt)) {
                $endAt = $endAt->addDay();
            }

            return max(0.0, round(((int) $startAt->diffInMinutes($endAt)) / 60, 4));
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * Raw clock minutes late vs class schedule start (ignores policy grace / waive).
     */
    public function clockLateMinutesForEntry(RawEmployeeLoadEntry $entry): int
    {
        if ($entry->session_date === null || $entry->time_in === null || $entry->time_in === '') {
            return 0;
        }

        $scheduleStart = $this->parseScheduleStart($entry->class_schedule);

        if ($scheduleStart === null) {
            return 0;
        }

        try {
            $sessionDate = CarbonImmutable::parse($entry->session_date->toDateString());
            $scheduledAt = $sessionDate->setTimeFromTimeString($scheduleStart);
            $timeInAt = $sessionDate->setTimeFromTimeString($entry->time_in);

            if ($timeInAt->lessThanOrEqualTo($scheduledAt)) {
                return 0;
            }

            return max(0, (int) $scheduledAt->diffInMinutes($timeInAt));
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param  Collection<int, RawEmployeeLoadEntry>  $entries
     */
    public function countWorkedDays(Collection $entries, ?TimekeepingPolicy $policy = null): int
    {
        return $entries
            ->groupBy(fn (RawEmployeeLoadEntry $entry) => $entry->session_date?->toDateString())
            ->filter(fn ($group, $date) => $date !== null && $date !== '')
            ->filter(function (Collection $dayEntries) use ($policy) {
                foreach ($dayEntries as $entry) {
                    if ($entry->time_in === null || $entry->time_in === '') {
                        continue;
                    }

                    $resolved = $this->resolvedLateForEntry($entry, $policy);

                    if (! $resolved['is_absent']) {
                        return true;
                    }
                }

                return false;
            })
            ->count();
    }

    /**
     * @param  Collection<int, RawEmployeeLoadEntry>  $entries
     */
    public function countAbsentSessions(Collection $entries, ?TimekeepingPolicy $policy = null): int
    {
        $count = 0;

        foreach ($entries as $entry) {
            if ($this->resolvedLateForEntry($entry, $policy)['is_absent']) {
                $count++;
            }
        }

        return $count;
    }

    public function dailyRate(EmployeeSalary $salary): ?float
    {
        $basicIncome = $salary->basicIncomeAmount();
        $daysPerPeriod = $salary->days_per_period;

        if ($daysPerPeriod === null || (float) $daysPerPeriod <= 0) {
            $daysPerPeriod = PayType::autoDaysPerPeriod((int) $salary->pay_type_id);
        }

        if ($basicIncome <= 0 || $daysPerPeriod === null || (float) $daysPerPeriod <= 0) {
            return null;
        }

        return (float) $basicIncome / (float) $daysPerPeriod;
    }

    /**
     * @param  Collection<int, RawEmployeeLoadEntry>  $entries
     */
    public function totalLateMinutes(Collection $entries, ?TimekeepingPolicy $policy = null): int
    {
        $total = 0;

        foreach ($entries as $entry) {
            $resolved = $this->resolvedLateForEntry($entry, $policy);

            if (! $resolved['is_absent']) {
                $total += $resolved['billable_minutes'];
            }
        }

        return $total;
    }

    /**
     * @param  Collection<int, RawEmployeeLoadEntry>  $entries
     */
    public function totalUndertimeMinutes(Collection $entries, ?TimekeepingPolicy $policy = null): int
    {
        $total = 0;

        foreach ($entries as $entry) {
            $total += $this->resolvedUndertimeForEntry($entry, $policy)['billable_minutes'];
        }

        return $total;
    }

    /**
     * @param  Collection<int, RawEmployeeLoadEntry>  $entries
     */
    public function countLateDays(Collection $entries, ?TimekeepingPolicy $policy = null): int
    {
        return $entries
            ->groupBy(fn (RawEmployeeLoadEntry $entry) => $entry->session_date?->toDateString())
            ->filter(function (Collection $dayEntries) use ($policy) {
                foreach ($dayEntries as $entry) {
                    $resolved = $this->resolvedLateForEntry($entry, $policy);

                    if (! $resolved['is_absent'] && $resolved['billable_minutes'] > 0) {
                        return true;
                    }
                }

                return false;
            })
            ->count();
    }

    /**
     * @param  Collection<int, RawEmployeeLoadEntry>  $entries
     */
    public function countUndertimeDays(Collection $entries, ?TimekeepingPolicy $policy = null): int
    {
        return $entries
            ->groupBy(fn (RawEmployeeLoadEntry $entry) => $entry->session_date?->toDateString())
            ->filter(function (Collection $dayEntries) use ($policy) {
                foreach ($dayEntries as $entry) {
                    if ($this->resolvedUndertimeForEntry($entry, $policy)['billable_minutes'] > 0) {
                        return true;
                    }
                }

                return false;
            })
            ->count();
    }

    /**
     * @return array{
     *     raw_minutes: int,
     *     equivalent_minutes: int|null,
     *     billable_minutes: int
     * }
     */
    public function resolvedUndertimeForEntry(RawEmployeeLoadEntry $entry, ?TimekeepingPolicy $policy = null): array
    {
        $raw = $this->undertimeMinutesForEntry($entry);

        return TimekeepingPolicySupport::resolveUndertimeEquivalent($policy?->timekeeping_policy_id, $raw);
    }

    /**
     * @return array{
     *     raw_minutes: int,
     *     equivalent_minutes: int|null,
     *     is_absent: bool,
     *     billable_minutes: int
     * }
     */
    public function resolvedLateForEntry(RawEmployeeLoadEntry $entry, ?TimekeepingPolicy $policy = null): array
    {
        $raw = $this->lateRawMinutesForEntry($entry, $policy);

        return $this->resolveLateMinutes($raw, $policy?->timekeeping_policy_id);
    }

    /**
     * @return array{
     *     raw_minutes: int,
     *     equivalent_minutes: int|null,
     *     is_absent: bool,
     *     billable_minutes: int
     * }
     */
    public function resolveLateMinutes(int $rawLateMinutes, ?int $timekeepingPolicyId = null): array
    {
        return TimekeepingPolicySupport::resolveTardinessEquivalent($timekeepingPolicyId, $rawLateMinutes);
    }

    public function lateMinutesForEntry(RawEmployeeLoadEntry $entry, ?TimekeepingPolicy $policy = null): int
    {
        return $this->lateRawMinutesForEntry($entry, $policy);
    }

    public function lateRawMinutesForEntry(RawEmployeeLoadEntry $entry, ?TimekeepingPolicy $policy = null): int
    {
        if ($entry->late_waived) {
            return 0;
        }

        if ($entry->session_date === null || $entry->time_in === null || $entry->time_in === '') {
            return 0;
        }

        $scheduleStart = $this->parseScheduleStart($entry->class_schedule);

        if ($scheduleStart === null) {
            return 0;
        }

        $sessionDate = CarbonImmutable::parse($entry->session_date->toDateString());
        $scheduledAt = $sessionDate->setTimeFromTimeString($scheduleStart);
        $timeInAt = $sessionDate->setTimeFromTimeString($entry->time_in);

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

    public function undertimeMinutesForEntry(RawEmployeeLoadEntry $entry): int
    {
        if ($entry->session_date === null || $entry->time_out === null || $entry->time_out === '') {
            return 0;
        }

        $scheduleEnd = $this->parseScheduleEnd($entry->class_schedule);

        if ($scheduleEnd === null) {
            return 0;
        }

        $sessionDate = CarbonImmutable::parse($entry->session_date->toDateString());
        $scheduledEnd = $sessionDate->setTimeFromTimeString($scheduleEnd);
        $timeOutAt = $sessionDate->setTimeFromTimeString($entry->time_out);

        if ($timeOutAt->greaterThanOrEqualTo($scheduledEnd)) {
            return 0;
        }

        return (int) $timeOutAt->diffInMinutes($scheduledEnd);
    }

    public function overtimeMinutesForEntry(RawEmployeeLoadEntry $entry): int
    {
        if ($entry->session_date === null || $entry->time_out === null || $entry->time_out === '') {
            return 0;
        }

        $scheduleEnd = $this->parseScheduleEnd($entry->class_schedule);

        if ($scheduleEnd === null) {
            return 0;
        }

        $sessionDate = CarbonImmutable::parse($entry->session_date->toDateString());
        $scheduledEnd = $sessionDate->setTimeFromTimeString($scheduleEnd);
        $timeOutAt = $sessionDate->setTimeFromTimeString($entry->time_out);

        if ($timeOutAt->lessThanOrEqualTo($scheduledEnd)) {
            return 0;
        }

        return (int) $scheduledEnd->diffInMinutes($timeOutAt);
    }

    /**
     * @return array{
     *     late_minutes: int,
     *     late_raw_minutes: int,
     *     late_is_absent: bool,
     *     late_display: string,
     *     undertime_minutes: int,
     *     overtime_minutes: int
     * }
     */
    public function attendanceMetricsForEntry(RawEmployeeLoadEntry $entry, ?TimekeepingPolicy $policy = null): array
    {
        $resolved = $this->resolvedLateForEntry($entry, $policy);
        $lateWaived = (bool) $entry->late_waived;
        $hadLate = $this->clockLateMinutesForEntry($entry) > 0;

        return [
            'late_minutes' => $resolved['billable_minutes'],
            'late_raw_minutes' => $resolved['raw_minutes'],
            'late_is_absent' => $resolved['is_absent'],
            'late_waived' => $lateWaived,
            'late_display' => ($lateWaived && $hadLate)
                ? 'Waived'
                : $this->formatLateDisplay($resolved),
            'hours' => $this->hoursForEntry($entry),
            'undertime_minutes' => $this->undertimeMinutesForEntry($entry),
            'overtime_minutes' => $this->overtimeMinutesForEntry($entry),
        ];
    }

    /**
     * @param  array{
     *     raw_minutes: int,
     *     equivalent_minutes: int|null,
     *     is_absent: bool,
     *     billable_minutes: int
     * }  $resolved
     */
    public function formatLateDisplay(array $resolved): string
    {
        if ($resolved['raw_minutes'] <= 0) {
            return '—';
        }

        if ($resolved['is_absent']) {
            return 'Absent';
        }

        return $this->formatDurationMinutes($resolved['billable_minutes']);
    }

    public function formatDurationMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '—';
        }

        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        if ($remainder === 0) {
            return $hours.' hr';
        }

        return $hours.' hr '.$remainder.' min';
    }

    public function parseScheduleStart(?string $classSchedule): ?string
    {
        if ($classSchedule === null || trim($classSchedule) === '') {
            return null;
        }

        $parts = preg_split('/\s*-\s*/', trim($classSchedule), 2);

        if (! is_array($parts) || ($parts[0] ?? '') === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse(trim($parts[0]))->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    public function parseScheduleEnd(?string $classSchedule): ?string
    {
        if ($classSchedule === null || trim($classSchedule) === '') {
            return null;
        }

        $parts = preg_split('/\s*-\s*/', trim($classSchedule), 2);

        if (! is_array($parts) || ($parts[1] ?? '') === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse(trim($parts[1]))->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    public function lateDeductionTypeId(): ?int
    {
        return DeductionType::query()
            ->whereIn('deduction_type_code', ['LTDE', 'OTHR'])
            ->orderByRaw("CASE deduction_type_code WHEN 'LTDE' THEN 0 ELSE 1 END")
            ->value('deduction_type_id');
    }

    public function undertimeDeductionTypeId(): ?int
    {
        return DeductionType::query()
            ->whereIn('deduction_type_code', ['UTDE', 'OTHR'])
            ->orderByRaw("CASE deduction_type_code WHEN 'UTDE' THEN 0 ELSE 1 END")
            ->value('deduction_type_id');
    }
}
