<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\RawEmployeeLoadEntry;
use App\Models\TimekeepingMemoSendLog;
use App\Models\TimekeepingMemoSetup;
use App\Models\TimekeepingPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TimekeepingMemoAttendanceService
{
    public function __construct(
        private readonly EmployeeAttendanceViewService $attendanceView,
        private readonly TimeLogsPayrollService $timeLogsPayroll,
        private readonly EmployeeShiftResolver $shiftResolver,
        private readonly TimekeepingMemoFacultyScheduleResolver $facultySchedule,
        private readonly EmployeeLoadPayrollService $employeeLoadPayroll,
    ) {}

    /**
     * @return list<string>
     */
    public function violationTypes(): array
    {
        return TimekeepingMemoSetup::TYPES;
    }

    public function normalizeViolationType(?string $type): string
    {
        $normalized = strtolower(trim((string) $type));

        return in_array($normalized, TimekeepingMemoSetup::TYPES, true)
            ? $normalized
            : TimekeepingMemoSetup::TYPE_LATE;
    }

    /**
     * @return list<array{
     *     work_date: string,
     *     time_in: string|null,
     *     time_out: string|null,
     *     minutes: int,
     *     memo_sent: bool
     * }>
     */
    public function violationDaysForEmployee(
        Employee $employee,
        string $dateFrom,
        string $dateTo,
        string $violationType,
    ): array {
        $violationType = $this->normalizeViolationType($violationType);
        $sentDates = $this->sentDatesForEmployee($employee->employee_id, $violationType, $dateFrom, $dateTo);

        if ($employee->isFaculty()) {
            return $this->facultyViolationDays($employee, $dateFrom, $dateTo, $violationType, $sentDates);
        }

        $days = $this->attendanceView->computeDaysForPersistence($employee, $dateFrom, $dateTo);
        $rows = [];

        foreach ($days as $day) {
            if ((bool) ($day['is_rest_day'] ?? false)) {
                continue;
            }

            $minutes = $this->violationMinutesForDay($day, $employee, $violationType);
            if ($minutes === null) {
                continue;
            }

            $workDate = (string) $day['date'];
            $rows[] = [
                'work_date' => $workDate,
                'time_in' => $day['time_in'] ?? null,
                'time_out' => $day['time_out'] ?? null,
                'minutes' => $minutes,
                'memo_sent' => isset($sentDates[$workDate]),
            ];
        }

        return $rows;
    }

    public function violationCountForEmployee(
        Employee $employee,
        string $dateFrom,
        string $dateTo,
        string $violationType,
    ): int {
        return count($this->violationDaysForEmployee($employee, $dateFrom, $dateTo, $violationType));
    }

    /**
     * @return Collection<int, string>
     */
    private function sentDatesForEmployee(int $employeeId, string $violationType, string $dateFrom, string $dateTo): Collection
    {
        return TimekeepingMemoSendLog::query()
            ->where('employee_id', $employeeId)
            ->where('violation_type', $this->normalizeViolationType($violationType))
            ->whereBetween('work_date', [$dateFrom, $dateTo])
            ->pluck('work_date')
            ->map(fn ($date) => $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date)
            ->flip();
    }

    /**
     * @param  array<string, mixed>  $day
     */
    private function violationMinutesForDay(array $day, Employee $employee, string $violationType): ?int
    {
        return match ($this->normalizeViolationType($violationType)) {
            TimekeepingMemoSetup::TYPE_LATE => $this->lateMinutes($day, $employee),
            TimekeepingMemoSetup::TYPE_UNDERTIME => $this->undertimeMinutes($day, $employee),
            TimekeepingMemoSetup::TYPE_ABSENT => $this->isAbsentDay($day, $employee) ? 0 : null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $day
     */
    private function lateMinutes(array $day, Employee $employee): ?int
    {
        if ($this->isAbsentDay($day, $employee)) {
            return null;
        }

        $lateHours = (float) ($day['late'] ?? 0);
        $breakLateHours = (float) ($day['break_late'] ?? 0);
        $minutes = (int) round(($lateHours + $breakLateHours) * 60);

        return $minutes > 0 ? $minutes : null;
    }

    /**
     * @param  array<string, mixed>  $day
     */
    private function undertimeMinutes(array $day, Employee $employee): ?int
    {
        if ($this->isAbsentDay($day, $employee)) {
            return null;
        }

        $minutes = (int) round(((float) ($day['undertime'] ?? 0)) * 60);

        return $minutes > 0 ? $minutes : null;
    }

    /**
     * @param  array<string, mixed>  $day
     */
    private function isAbsentDay(array $day, ?Employee $employee = null): bool
    {
        if ((bool) ($day['is_rest_day'] ?? false)) {
            return false;
        }

        if (! (bool) ($day['has_logs'] ?? false)) {
            return filled($day['shift_label'] ?? null) || filled($day['shift_code_id'] ?? null);
        }

        if ($employee === null) {
            return false;
        }

        $employee->loadMissing('timekeepingSetup.policy', 'timekeepingSetup.shiftCode');
        $policy = $employee->timekeepingSetup?->policy;
        $defaultShift = $employee->timekeepingSetup?->shiftCode;
        $sessionDate = CarbonImmutable::parse((string) ($day['date'] ?? ''));
        $shift = $this->shiftResolver->forDate($employee, $sessionDate, $defaultShift);

        if ($shift !== null && (bool) $shift->is_flexi_time) {
            return false;
        }

        $timeIn = $day['time_in_raw'] ?? null;
        if ($timeIn === null || $timeIn === '') {
            return true;
        }

        $session = [
            'date' => $sessionDate,
            'time_in' => $timeIn,
            'time_out' => $day['time_out_raw'] ?? null,
        ];

        $resolved = $this->timeLogsPayroll->resolvedLateForSession(
            $session,
            $policy,
            $shift?->time_in ?? $defaultShift?->time_in,
            0,
        );

        return (bool) ($resolved['is_absent'] ?? false);
    }

    /**
     * @param  Collection<string, mixed>  $sentDates
     * @return list<array{
     *     work_date: string,
     *     time_in: string|null,
     *     time_out: string|null,
     *     minutes: int,
     *     memo_sent: bool
     * }>
     */
    private function facultyViolationDays(
        Employee $employee,
        string $dateFrom,
        string $dateTo,
        string $violationType,
        Collection $sentDates,
    ): array {
        $sessionsByDate = $this->facultySchedule->sessionsByDate($employee, $dateFrom, $dateTo);

        if ($sessionsByDate->isEmpty()) {
            return [];
        }

        $employee->loadMissing('timekeepingSetup.policy');
        /** @var TimekeepingPolicy|null $policy */
        $policy = $employee->timekeepingSetup?->policy;

        $loadEntries = RawEmployeeLoadEntry::query()
            ->where('employee_id', (int) $employee->employee_id)
            ->whereDate('session_date', '>=', $dateFrom)
            ->whereDate('session_date', '<=', $dateTo)
            ->get()
            ->groupBy(fn (RawEmployeeLoadEntry $entry) => $entry->session_date?->toDateString() ?? '');

        $rows = [];

        foreach ($sessionsByDate as $workDate => $daySessions) {
            if ($workDate === '') {
                continue;
            }

            $minutes = match ($violationType) {
                TimekeepingMemoSetup::TYPE_ABSENT => $this->facultyAbsentMinutesForDay($daySessions),
                TimekeepingMemoSetup::TYPE_LATE => $this->facultyLateMinutesForDay(
                    $loadEntries->get($workDate, collect()),
                    $daySessions,
                    $policy,
                ),
                TimekeepingMemoSetup::TYPE_UNDERTIME => $this->facultyUndertimeMinutesForDay(
                    $loadEntries->get($workDate, collect()),
                    $policy,
                ),
                default => null,
            };

            if ($minutes === null) {
                continue;
            }

            [$timeIn, $timeOut] = $this->facultyDisplayTimesForDay($daySessions, $loadEntries->get($workDate, collect()));

            $rows[] = [
                'work_date' => (string) $workDate,
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'minutes' => $minutes,
                'memo_sent' => $sentDates->has((string) $workDate),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $daySessions
     */
    private function facultyAbsentMinutesForDay(Collection $daySessions): ?int
    {
        foreach ($daySessions as $session) {
            if (! is_array($session)) {
                continue;
            }

            if (! filled($session['class_schedule'] ?? null)) {
                continue;
            }

            if (! (bool) ($session['has_real_attendance'] ?? false)) {
                return 0;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, RawEmployeeLoadEntry>  $dayEntries
     * @param  Collection<int, array<string, mixed>>  $daySessions
     */
    private function facultyLateMinutesForDay(
        Collection $dayEntries,
        Collection $daySessions,
        ?TimekeepingPolicy $policy,
    ): ?int {
        if ($this->facultyAbsentMinutesForDay($daySessions) === 0) {
            return null;
        }

        $minutes = 0;

        foreach ($dayEntries as $entry) {
            if (! $entry instanceof RawEmployeeLoadEntry) {
                continue;
            }

            if ($entry->time_in === null || $entry->time_in === '') {
                continue;
            }

            $resolved = $this->employeeLoadPayroll->resolvedLateForEntry($entry, $policy);

            if ($resolved['is_absent']) {
                return null;
            }

            $minutes += (int) ($resolved['billable_minutes'] ?? 0);
        }

        return $minutes > 0 ? $minutes : null;
    }

    /**
     * @param  Collection<int, RawEmployeeLoadEntry>  $dayEntries
     */
    private function facultyUndertimeMinutesForDay(Collection $dayEntries, ?TimekeepingPolicy $policy): ?int
    {
        $minutes = 0;

        foreach ($dayEntries as $entry) {
            if (! $entry instanceof RawEmployeeLoadEntry) {
                continue;
            }

            if ($entry->time_in === null || $entry->time_in === '') {
                continue;
            }

            $resolved = $this->employeeLoadPayroll->resolvedLateForEntry($entry, $policy);

            if ($resolved['is_absent']) {
                return null;
            }

            $minutes += $this->employeeLoadPayroll->undertimeMinutesForEntry($entry);
        }

        return $minutes > 0 ? $minutes : null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $daySessions
     * @param  Collection<int, RawEmployeeLoadEntry>  $dayEntries
     * @return array{0: ?string, 1: ?string}
     */
    private function facultyDisplayTimesForDay(Collection $daySessions, Collection $dayEntries): array
    {
        $entry = $dayEntries
            ->first(fn (RawEmployeeLoadEntry $row) => filled($row->time_in));

        if ($entry !== null) {
            return [
                $this->formatDisplayTime($entry->time_in),
                $this->formatDisplayTime($entry->time_out),
            ];
        }

        foreach ($daySessions as $session) {
            if (! is_array($session)) {
                continue;
            }

            if (filled($session['time_in'] ?? null)) {
                return [
                    $this->formatDisplayTime($session['time_in']),
                    $this->formatDisplayTime($session['time_out'] ?? null),
                ];
            }
        }

        return [null, null];
    }

    private function formatDisplayTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->format('H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    public function campusLabel(Employee $employee): string
    {
        $employee->loadMissing(['campus', 'campusAssignments.campus']);

        $primary = $employee->campusAssignments
            ->firstWhere('is_primary', true)
            ?? $employee->campusAssignments->first();

        if ($primary?->campus) {
            return trim((string) $primary->campus->campus_name);
        }

        return trim((string) ($employee->campus?->campus_name ?? $employee->campus ?? '—'));
    }
}
