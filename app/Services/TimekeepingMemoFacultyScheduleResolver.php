<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\RawEmployeeLoadEntry;
use App\Models\TeachingLoadSession;
use App\Support\SkolarisCheckerLoadStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Scheduled class sessions for faculty memo (local DB + optional Skolaris daily-loads cache).
 */
class TimekeepingMemoFacultyScheduleResolver
{
    /** @var array<string, array<string, list<array<string, mixed>>>> */
    private static array $skolarisLoadsByRange = [];

    public function __construct(private readonly SkolarisApiService $skolaris) {}

    /**
     * @return Collection<string, Collection<int, array{
     *     session_date: string,
     *     class_schedule: ?string,
     *     time_in: ?string,
     *     time_out: ?string,
     *     has_real_attendance: bool
     * }>>
     */
    public function sessionsByDate(Employee $employee, string $dateFrom, string $dateTo): Collection
    {
        $employeeId = (int) $employee->employee_id;
        $sessions = collect();

        $entries = RawEmployeeLoadEntry::query()
            ->where('employee_id', $employeeId)
            ->whereDate('session_date', '>=', $dateFrom)
            ->whereDate('session_date', '<=', $dateTo)
            ->orderBy('session_date')
            ->get();

        foreach ($entries as $entry) {
            if (! $entry instanceof RawEmployeeLoadEntry || $entry->session_date === null) {
                continue;
            }

            $dateKey = $entry->session_date->toDateString();
            $sessions->push($this->sessionFromLoadEntry($dateKey, $entry));
        }

        if ($sessions->isEmpty()) {
            $pulled = TeachingLoadSession::query()
                ->where('employee_id', $employeeId)
                ->whereDate('session_date', '>=', $dateFrom)
                ->whereDate('session_date', '<=', $dateTo)
                ->orderBy('session_date')
                ->get();

            foreach ($pulled as $session) {
                if ($session->session_date === null) {
                    continue;
                }

                $sessions->push($this->sessionFromTeachingLoad(
                    $session->session_date->toDateString(),
                    $session->class_schedule,
                    $session->time_in,
                    $session->time_out,
                    $session->status_code,
                ));
            }
        }

        if ($sessions->isEmpty()) {
            $sessions = $this->sessionsFromSkolarisCache($employee, $dateFrom, $dateTo);
        }

        return $sessions
            ->filter(fn (array $row) => filled($row['class_schedule'] ?? null) || filled($row['session_date'] ?? null))
            ->groupBy(fn (array $row) => (string) $row['session_date']);
    }

    /**
     * @return array{
     *     session_date: string,
     *     class_schedule: ?string,
     *     time_in: ?string,
     *     time_out: ?string,
     *     has_real_attendance: bool
     * }
     */
    private function sessionFromLoadEntry(string $dateKey, RawEmployeeLoadEntry $entry): array
    {
        $timeIn = $this->normalizeClock($entry->time_in);
        $timeOut = $this->normalizeClock($entry->time_out);

        return [
            'session_date' => $dateKey,
            'class_schedule' => $entry->class_schedule,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'has_real_attendance' => $timeIn !== null,
        ];
    }

    /**
     * @return array{
     *     session_date: string,
     *     class_schedule: ?string,
     *     time_in: ?string,
     *     time_out: ?string,
     *     has_real_attendance: bool
     * }
     */
    private function sessionFromTeachingLoad(
        string $dateKey,
        ?string $classSchedule,
        ?string $timeIn,
        ?string $timeOut,
        ?string $statusCode,
    ): array {
        $timeInNorm = $this->normalizeClock($timeIn);
        $timeOutNorm = $this->normalizeClock($timeOut);

        $hasReal = SkolarisCheckerLoadStatus::countsAsPresent($statusCode);

        return [
            'session_date' => $dateKey,
            'class_schedule' => $classSchedule,
            'time_in' => $timeInNorm,
            'time_out' => $timeOutNorm,
            'has_real_attendance' => $hasReal,
        ];
    }

    /**
     * @return Collection<int, array{
     *     session_date: string,
     *     class_schedule: ?string,
     *     time_in: ?string,
     *     time_out: ?string,
     *     has_real_attendance: bool
     * }>
     */
    private function sessionsFromSkolarisCache(Employee $employee, string $dateFrom, string $dateTo): Collection
    {
        if (! (bool) config('timekeeping.memo.faculty_use_skolaris_schedule', true)) {
            return collect();
        }

        $employeeNumber = trim((string) $employee->employee_number);
        if ($employeeNumber === '') {
            return collect();
        }

        $rangeKey = $dateFrom.'|'.$dateTo;
        if (! isset(self::$skolarisLoadsByRange[$rangeKey])) {
            self::$skolarisLoadsByRange[$rangeKey] = $this->indexSkolarisDailyLoads($dateFrom, $dateTo);
        }

        $loads = self::$skolarisLoadsByRange[$rangeKey][$employeeNumber] ?? [];
        $sessions = collect();

        foreach ($loads as $load) {
            if (! is_array($load)) {
                continue;
            }

            $dateKey = trim((string) ($load['attendance_date'] ?? $load['session_date'] ?? $load['date'] ?? ''));
            if ($dateKey === '') {
                continue;
            }

            try {
                $sessionDate = CarbonImmutable::parse($dateKey)->toDateString();
            } catch (\Throwable) {
                continue;
            }

            if ($sessionDate < $dateFrom || $sessionDate > $dateTo) {
                continue;
            }

            $classSchedule = $this->classScheduleFromSkolarisLoad($load);
            $actualIn = $this->normalizeClock($load['actual_time_in'] ?? null);
            $logIn = $this->normalizeClock($load['log_time_in'] ?? null);
            $status = $load['status'] ?? $load['status_code'] ?? null;
            $hasReal = ($actualIn !== null || $logIn !== null)
                || SkolarisCheckerLoadStatus::countsAsPresent($status);

            $sessions->push([
                'session_date' => $sessionDate,
                'class_schedule' => $classSchedule,
                'time_in' => $actualIn ?? $logIn,
                'time_out' => $this->normalizeClock($load['actual_time_out'] ?? $load['log_time_out'] ?? null),
                'has_real_attendance' => $hasReal,
            ]);
        }

        return $sessions;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function indexSkolarisDailyLoads(string $dateFrom, string $dateTo): array
    {
        try {
            $rows = $this->skolaris->dailyLoadsForEmployeeLoadTemplate($dateFrom, $dateTo);
        } catch (\Throwable) {
            return [];
        }

        $indexed = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $number = trim((string) ($row['employee_number'] ?? ''));
            if ($number === '') {
                continue;
            }

            $loads = $row['loads'] ?? [];
            $indexed[$number] = is_array($loads) ? array_values($loads) : [];
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $load
     */
    private function classScheduleFromSkolarisLoad(array $load): ?string
    {
        $start = trim((string) ($load['schedule_time_start'] ?? $load['start_time'] ?? ''));
        $end = trim((string) ($load['schedule_time_end'] ?? $load['end_time'] ?? ''));

        if ($start !== '' && $end !== '') {
            return $start.' - '.$end;
        }

        $schedule = trim((string) ($load['class_schedule'] ?? $load['schedule'] ?? ''));

        return $schedule !== '' ? $schedule : null;
    }

    private function normalizeClock(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
