<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LuDay;
use App\Models\TeachingLoadSession;
use App\Support\EmployeeNumberMatch;
use App\Support\SkolarisLoadSessionTimes;
use App\Support\SkolarisScheduleWeekdays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Compares consultation hours with the faculty's weekly classes from Skolaris.
 * Read-only: does not overwrite stored teaching loads or Employee Load entries.
 */
class FacultyConsultationConflictService
{
    public const SOURCE_SKOLARIS = 'skolaris';

    public const SOURCE_STORED = 'stored';

    public const SOURCE_NONE = 'none';

    public const SOURCE_SKIPPED = 'skipped';

    public function __construct(
        private readonly SkolarisApiService $skolaris,
    ) {}

    /**
     * @param  array<int, array{day_id: int, time_start: string, time_end: string}>  $slots  H:i times
     * @return array{source: string, conflicts: array<int, string>}
     */
    public function check(Employee $employee, array $slots, ?CarbonImmutable $today = null): array
    {
        if ($slots === []) {
            return ['source' => self::SOURCE_SKIPPED, 'conflicts' => []];
        }

        $today ??= CarbonImmutable::today();
        $from = $today->startOfWeek(CarbonImmutable::MONDAY)->subWeeks(3);
        $to = $today->startOfWeek(CarbonImmutable::MONDAY)->addDays(13);

        $source = self::SOURCE_SKOLARIS;
        $classes = $this->classesFromSkolaris($employee, $from->toDateString(), $to->toDateString());

        if ($classes === null || $classes === []) {
            $stored = $this->classesFromStoredLoad($employee);

            if ($stored !== []) {
                $classes = $stored;
                $source = self::SOURCE_STORED;
            } elseif ($classes === null) {
                return ['source' => self::SOURCE_NONE, 'conflicts' => []];
            }
        }

        return [
            'source' => $source,
            'conflicts' => $this->conflictMessages($slots, $classes),
        ];
    }

    /**
     * Fast check for bulk uploads: compares with the last pulled teaching load only (no Skolaris call).
     *
     * @param  array<int, array{day_id: int, time_start: string, time_end: string}>  $slots
     * @return array<int, string>
     */
    public function checkStoredLoad(Employee $employee, array $slots): array
    {
        if ($slots === []) {
            return [];
        }

        return $this->conflictMessages($slots, $this->classesFromStoredLoad($employee));
    }

    /**
     * @param  array<int, array{day_id: int, time_start: string, time_end: string}>  $slots
     * @param  array<int, array{day_id: int, time_in: string, time_out: string, label: string}>  $classes
     * @return array<int, string>
     */
    private function conflictMessages(array $slots, array $classes): array
    {
        if ($classes === []) {
            return [];
        }

        $dayNames = LuDay::query()->pluck('day', 'day_id');
        $conflicts = [];

        $slots = collect($slots)->sortBy(fn (array $slot) => [
            ((int) $slot['day_id'] + 5) % 7,
            $slot['time_start'],
        ])->values();

        foreach ($slots as $slot) {
            $overlapping = collect($classes)
                ->filter(fn (array $class) => $class['day_id'] === (int) $slot['day_id']
                    && $slot['time_start'] < $class['time_out']
                    && $class['time_in'] < $slot['time_end'])
                ->sortBy(fn (array $class) => [$class['time_in'], $class['label']])
                ->map(fn (array $class) => sprintf(
                    '%s (%s–%s)',
                    $class['label'],
                    $this->clock($class['time_in']),
                    $this->clock($class['time_out']),
                ))
                ->unique()
                ->values();

            if ($overlapping->isNotEmpty()) {
                $conflicts[] = sprintf(
                    '%s %s–%s conflicts with %s.',
                    $dayNames->get((int) $slot['day_id'], 'Day'),
                    $this->clock($slot['time_start']),
                    $this->clock($slot['time_end']),
                    $overlapping->implode(', '),
                );
            }
        }

        return array_values(array_unique($conflicts));
    }

    /**
     * @return array<int, array{day_id: int, time_in: string, time_out: string, label: string}>|null null when Skolaris could not be reached
     */
    private function classesFromSkolaris(Employee $employee, string $from, string $to): ?array
    {
        $localNumber = trim((string) $employee->employee_number);

        if ($localNumber === '') {
            return null;
        }

        try {
            $record = $this->skolaris->resolveSkolarisEmployeeRecord($employee);
            $skolarisId = (int) ($record['employee_id'] ?? 0);
            $skolarisNumber = trim((string) ($record['employee_number'] ?? ''));

            $attendanceLoads = $skolarisId > 0
                ? $this->skolaris->timekeepingEmployeeAttendance($skolarisId, $from, $to)['logs']
                : [];

            if ($attendanceLoads !== []) {
                return $this->uniqueClasses(array_map(fn (array $load) => $this->classFromSkolarisLoad($load), $attendanceLoads));
            }

            $numbers = array_values(array_unique(array_filter([$localNumber, $skolarisNumber])));
            $rows = $this->skolaris->dailyLoads($from, $to, $numbers);
        } catch (Throwable $exception) {
            Log::warning('Consultation hours conflict check could not reach Skolaris', [
                'employee_id' => $employee->employee_id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        $loads = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! is_array($row['loads'] ?? null)) {
                continue;
            }

            $rowNumber = trim((string) ($row['employee_number'] ?? ''));

            if ($rowNumber !== $localNumber
                && ($skolarisNumber === '' || ! EmployeeNumberMatch::same($rowNumber, $skolarisNumber))) {
                continue;
            }

            foreach ($row['loads'] as $load) {
                if (is_array($load)) {
                    $loads[] = $this->classFromSkolarisLoad($load);
                }
            }
        }

        return $this->uniqueClasses($loads);
    }

    /**
     * @param  array<string, mixed>  $load
     * @return array{day_id: int, date: ?string, time_in: string, time_out: string, label: string}|null
     */
    private function classFromSkolarisLoad(array $load): ?array
    {
        $times = SkolarisLoadSessionTimes::forEmployeeLoadEntry(array_diff_key($load, array_flip([
            'actual_time_in', 'actual_time_out', 'log_time_in', 'log_time_out',
        ])));

        return $this->classRow(
            $load['attendance_date'] ?? null,
            $load['schedule_day'] ?? null,
            $times['time_in'],
            $times['time_out'],
            $load['subject_code'] ?? null,
            $load['section'] ?? null,
        );
    }

    /**
     * @return array<int, array{day_id: int, time_in: string, time_out: string, label: string}>
     */
    private function classesFromStoredLoad(Employee $employee): array
    {
        $latestTo = TeachingLoadSession::query()
            ->where('employee_id', $employee->employee_id)
            ->max('date_to');

        if ($latestTo === null) {
            return [];
        }

        $sessions = TeachingLoadSession::query()
            ->where('employee_id', $employee->employee_id)
            ->whereDate('date_to', substr((string) $latestTo, 0, 10))
            ->get();

        return $this->uniqueClasses($sessions->map(fn (TeachingLoadSession $session) => $this->classRow(
            $session->session_date?->toDateString(),
            $session->schedule_day,
            $session->time_in,
            $session->time_out,
            $session->subject_code,
            $session->section,
        ))->all());
    }

    /**
     * @return array{day_id: int, time_in: string, time_out: string, label: string}|null
     */
    private function classRow(mixed $date, mixed $scheduleDay, ?string $timeIn, ?string $timeOut, mixed $subjectCode, mixed $section): ?array
    {
        $timeIn = $this->hourMinute($timeIn);
        $timeOut = $this->hourMinute($timeOut);

        if ($timeIn === null || $timeOut === null || $timeOut <= $timeIn) {
            return null;
        }

        $dayId = null;
        $sessionDate = null;

        if (filled($date)) {
            try {
                $parsed = CarbonImmutable::parse((string) $date);
                $dayId = LuDay::idFromDate($parsed);
                $sessionDate = $parsed->toDateString();
            } catch (Throwable) {
                $dayId = null;
            }
        }

        if ($dayId === null) {
            $isoWeekday = SkolarisScheduleWeekdays::fromDayField(is_string($scheduleDay) ? $scheduleDay : null)[0] ?? null;
            $dayId = $isoWeekday !== null ? ($isoWeekday % 7) + 1 : null;
        }

        if ($dayId === null) {
            return null;
        }

        $subject = trim((string) $subjectCode);
        $section = trim((string) $section);

        return [
            'day_id' => (int) $dayId,
            'date' => $sessionDate,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'label' => trim(($subject !== '' ? $subject : 'a class').($section !== '' ? ' '.$section : '')),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>|null>  $classes
     * @return array<int, array{day_id: int, time_in: string, time_out: string, label: string}>
     */
    private function uniqueClasses(array $classes): array
    {
        $classes = array_filter($classes);
        $latestDateByDay = [];

        foreach ($classes as $class) {
            if ($class['date'] !== null && ($latestDateByDay[$class['day_id']] ?? '') < $class['date']) {
                $latestDateByDay[$class['day_id']] = $class['date'];
            }
        }

        $unique = [];

        foreach ($classes as $class) {
            $latest = $latestDateByDay[$class['day_id']] ?? null;

            if ($latest !== null && $class['date'] !== null && $class['date'] !== $latest) {
                continue;
            }

            $unique[implode('|', [$class['day_id'], $class['time_in'], $class['time_out'], $class['label']])] = $class;
        }

        return array_values($unique);
    }

    private function hourMinute(?string $time): ?string
    {
        $time = trim((string) $time);

        return preg_match('/^\d{2}:\d{2}/', $time) ? substr($time, 0, 5) : null;
    }

    private function clock(string $time): string
    {
        return CarbonImmutable::createFromFormat('H:i', $time)->format('g:i A');
    }
}
