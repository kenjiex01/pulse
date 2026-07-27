<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\RawEmployeeLoadEntry;
use App\Models\ShiftCode;
use App\Models\TimekeepingPolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Faculty payroll: compute teaching-load Hours when the day has attendance logs.
 * Late is based on the first Time In log of the day vs shift code (fallback: earliest class schedule).
 */
class FacultyLoadPayrollService
{
    public function __construct(
        private readonly EmployeeLoadPayrollService $employeeLoadPayroll,
        private readonly TimeLogsPayrollService $timeLogsPayroll,
        private readonly FlexiShiftPayrollService $flexiShiftPayroll,
    ) {}

    /**
     * Faculty basic pay is always driven by teaching loads (never Leaves / fixed BASC).
     * No loads in the period ⇒ basic stays 0 (faculty have no auto-log policy).
     */
    public function shouldUseFacultyLoadPath(
        ?Employee $employee,
        EmployeeSalary $salary,
        CarbonInterface $from,
        CarbonInterface $to,
    ): bool {
        unset($salary, $from, $to);

        return $employee !== null && $employee->isFaculty();
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
     *     absent_sessions: int,
     *     computed_hours: float
     * }
     */
    public function computeForPeriod(
        EmployeeSalary $salary,
        Employee $employee,
        CarbonInterface $from,
        CarbonInterface $to,
        ?TimekeepingPolicy $policy = null,
        ?ShiftCode $shiftCode = null,
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
        ];

        $entries = $this->employeeLoadPayroll->allEntriesForEmployeeInPeriod(
            (int) $employee->employee_id,
            $employee->employee_number,
            $from,
            $to,
        );

        if ($entries->isEmpty()) {
            return $empty;
        }

        $dayPunches = $this->timeLogsPayroll->dayPunchesForPeriod(
            (int) $employee->employee_id,
            $from,
            $to,
        );

        $isFlexi = $this->flexiShiftPayroll->isFlexiShift($shiftCode);
        $workedDays = 0;
        $lateMinutes = 0;
        $undertimeMinutes = 0;
        $absentSessions = 0;
        $computedHours = 0.0;

        foreach ($entries->groupBy(fn (RawEmployeeLoadEntry $entry) => $entry->session_date?->toDateString()) as $date => $dayEntries) {
            if ($date === null || $date === '') {
                continue;
            }

            $punches = $dayPunches->get($date, collect());
            $entriesWithTimes = $dayEntries->filter(
                fn (RawEmployeeLoadEntry $entry) => $entry->time_in !== null && $entry->time_in !== ''
                    && $entry->time_out !== null && $entry->time_out !== '',
            );

            // Uploaded loads use Time In/Out on the row; Skolaris pulls need day attendance logs.
            if ($entriesWithTimes->isEmpty() && $punches->isEmpty()) {
                continue;
            }

            $dayHours = 0.0;
            $dayLate = 0;
            $dayUndertime = 0;

            if ($entriesWithTimes->isNotEmpty()) {
                foreach ($entriesWithTimes as $entry) {
                    $resolvedLate = $isFlexi
                        ? ['is_absent' => false, 'billable_minutes' => 0]
                        : $this->employeeLoadPayroll->resolvedLateForEntry($entry, $policy);

                    if ($resolvedLate['is_absent']) {
                        $absentSessions++;

                        continue;
                    }

                    $entryHours = $this->employeeLoadPayroll->hoursForEntry($entry);

                    if ($entryHours <= 0) {
                        continue;
                    }

                    $dayHours += $entryHours;

                    if (! $isFlexi) {
                        $dayLate += (int) ($resolvedLate['billable_minutes'] ?? 0);
                        $dayUndertime += $this->employeeLoadPayroll->undertimeMinutesForEntry($entry);
                    }
                }
            } else {
                $scheduleStart = $this->lateScheduleStart($dayEntries, $shiftCode);
                $scheduleEnd = $this->undertimeScheduleEnd($dayEntries, $shiftCode);
                $session = [
                    'date' => CarbonImmutable::parse($date),
                    'time_in' => $this->firstTimeIn($punches),
                    'time_out' => $this->lastTimeOut($punches),
                ];

                $resolvedLate = $isFlexi
                    ? ['is_absent' => false, 'billable_minutes' => 0]
                    : $this->timeLogsPayroll->resolvedLateForSession($session, $policy, $scheduleStart);

                if ($resolvedLate['is_absent']) {
                    $absentSessions++;

                    continue;
                }

                foreach ($dayEntries as $entry) {
                    $dayHours += $this->employeeLoadPayroll->hoursForEntry($entry);
                }

                if (! $isFlexi) {
                    $dayLate += (int) ($resolvedLate['billable_minutes'] ?? 0);
                    $dayUndertime += $this->timeLogsPayroll->resolvedUndertimeForSession(
                        $session,
                        $policy,
                        $scheduleEnd,
                    )['billable_minutes'];
                }
            }

            if ($dayHours <= 0) {
                continue;
            }

            $computedHours += $dayHours;
            $workedDays++;
            $lateMinutes += $dayLate;
            $undertimeMinutes += $dayUndertime;
        }

        $hourlyRate = $salary->hourlyRate();
        $basicTaxable = ($hourlyRate !== null && $computedHours > 0)
            ? round($computedHours * $hourlyRate, 2)
            : 0.0;

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
        ];
    }

    /**
     * @return Collection<int, object{day_type_id: int, time_type_id: int, hours: float, time_type_code: string|null}>
     */
    public function hourTotalsForPeriod(
        Employee $employee,
        CarbonInterface $from,
        CarbonInterface $to,
        ?TimekeepingPolicy $policy = null,
        ?ShiftCode $shiftCode = null,
    ): array {
        $entries = $this->employeeLoadPayroll->allEntriesForEmployeeInPeriod(
            (int) $employee->employee_id,
            $employee->employee_number,
            $from,
            $to,
        );

        $dayPunches = $this->timeLogsPayroll->dayPunchesForPeriod(
            (int) $employee->employee_id,
            $from,
            $to,
        );

        $isFlexi = $this->flexiShiftPayroll->isFlexiShift($shiftCode);
        $totalHours = 0.0;

        foreach ($entries->groupBy(fn (RawEmployeeLoadEntry $entry) => $entry->session_date?->toDateString()) as $date => $dayEntries) {
            if ($date === null || $date === '') {
                continue;
            }

            $punches = $dayPunches->get($date, collect());
            $entriesWithTimes = $dayEntries->filter(
                fn (RawEmployeeLoadEntry $entry) => $entry->time_in !== null && $entry->time_in !== ''
                    && $entry->time_out !== null && $entry->time_out !== '',
            );

            if ($entriesWithTimes->isEmpty() && $punches->isEmpty()) {
                continue;
            }

            if ($entriesWithTimes->isNotEmpty()) {
                foreach ($entriesWithTimes as $entry) {
                    if (! $isFlexi) {
                        $resolvedLate = $this->employeeLoadPayroll->resolvedLateForEntry($entry, $policy);

                        if ($resolvedLate['is_absent']) {
                            continue;
                        }
                    }

                    $totalHours += $this->employeeLoadPayroll->hoursForEntry($entry);
                }

                continue;
            }

            $scheduleStart = $this->lateScheduleStart($dayEntries, $shiftCode);
            $firstIn = $this->firstTimeIn($punches);
            $session = [
                'date' => CarbonImmutable::parse($date),
                'time_in' => $firstIn,
                'time_out' => $this->lastTimeOut($punches),
            ];

            if (! $isFlexi) {
                $resolvedLate = $this->timeLogsPayroll->resolvedLateForSession($session, $policy, $scheduleStart);

                if ($resolvedLate['is_absent']) {
                    continue;
                }
            }

            foreach ($dayEntries as $entry) {
                $totalHours += $this->employeeLoadPayroll->hoursForEntry($entry);
            }
        }

        return ['basic_hours' => round($totalHours, 4)];
    }

    /**
     * @param  Collection<int, RawEmployeeLoadEntry>  $dayEntries
     */
    private function lateScheduleStart(Collection $dayEntries, ?ShiftCode $shiftCode): ?string
    {
        $shiftStart = trim((string) ($shiftCode?->time_in ?? ''));

        if ($shiftStart !== '') {
            return $shiftStart;
        }

        $starts = $dayEntries
            ->map(fn (RawEmployeeLoadEntry $entry) => $this->employeeLoadPayroll->parseScheduleStart($entry->class_schedule))
            ->filter()
            ->sort()
            ->values();

        return $starts->first();
    }

    /**
     * @param  Collection<int, RawEmployeeLoadEntry>  $dayEntries
     */
    private function undertimeScheduleEnd(Collection $dayEntries, ?ShiftCode $shiftCode): ?string
    {
        $shiftEnd = trim((string) ($shiftCode?->time_out ?? ''));

        if ($shiftEnd !== '') {
            return $shiftEnd;
        }

        $ends = $dayEntries
            ->map(fn (RawEmployeeLoadEntry $entry) => $this->employeeLoadPayroll->parseScheduleEnd($entry->class_schedule))
            ->filter()
            ->sort()
            ->values();

        return $ends->last();
    }

    /**
     * @param  Collection<int, \App\Models\RawTimekeepingInandout>  $punches
     */
    private function firstTimeIn(Collection $punches): ?string
    {
        $in = $punches->first(fn ($punch) => (bool) $punch->is_in && $punch->dt_datetime !== null);

        return $in?->dt_datetime?->format('H:i:s');
    }

    /**
     * @param  Collection<int, \App\Models\RawTimekeepingInandout>  $punches
     */
    private function lastTimeOut(Collection $punches): ?string
    {
        $out = $punches->reverse()->first(fn ($punch) => ! (bool) $punch->is_in && $punch->dt_datetime !== null);

        return $out?->dt_datetime?->format('H:i:s');
    }
}
