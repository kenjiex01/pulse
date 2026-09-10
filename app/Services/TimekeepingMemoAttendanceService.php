<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TimekeepingMemoSendLog;
use App\Models\TimekeepingMemoSetup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TimekeepingMemoAttendanceService
{
    public function __construct(
        private readonly EmployeeAttendanceViewService $attendanceView,
        private readonly TimeLogsPayrollService $timeLogsPayroll,
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
        $days = $this->attendanceView->computeDaysForPersistence($employee, $dateFrom, $dateTo);
        $sentDates = $this->sentDatesForEmployee($employee->employee_id, $violationType, $dateFrom, $dateTo);
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
        $shift = $employee->timekeepingSetup?->shiftCode;

        if ($shift !== null && (bool) $shift->is_flexi_time) {
            return false;
        }

        $timeIn = $day['time_in_raw'] ?? null;
        if ($timeIn === null || $timeIn === '') {
            return true;
        }

        $session = [
            'date' => CarbonImmutable::parse((string) $day['date']),
            'time_in' => $timeIn,
            'time_out' => $day['time_out_raw'] ?? null,
        ];

        $resolved = $this->timeLogsPayroll->resolvedLateForSession(
            $session,
            $policy,
            $shift?->time_in,
            0,
        );

        return (bool) ($resolved['is_absent'] ?? false);
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
