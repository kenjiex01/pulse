<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Collapse Skolaris Employee Attendance logs the way the timekeeping attendance
 * page does: one row per date and schedule window, hours counted once.
 */
final class SkolarisAttendancePageLoads
{
    /**
     * @param  array<int, mixed>  $logs
     * @return list<array<string, mixed>>
     */
    public static function rows(array $logs): array
    {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];

        foreach ($logs as $log) {
            if (! is_array($log)) {
                continue;
            }

            $date = substr(trim((string) ($log['attendance_date'] ?? '')), 0, 10);

            if ($date === '') {
                continue;
            }

            $timeIn = trim((string) ($log['time_in'] ?? $log['scheduled_time_in'] ?? ''));
            $timeOut = trim((string) ($log['time_out'] ?? $log['scheduled_time_out'] ?? ''));
            $day = trim((string) ($log['schedule_day'] ?? ''));
            $key = $date.'|'.$timeIn.'|'.$timeOut.'|'.$day;

            if (! isset($groups[$key])) {
                $groups[$key] = $log;
                $groups[$key]['attendance_date'] = $date;
                $groups[$key]['time_in'] = $timeIn !== '' ? $timeIn : null;
                $groups[$key]['time_out'] = $timeOut !== '' ? $timeOut : null;
                unset(
                    $groups[$key]['actual_time_in'],
                    $groups[$key]['actual_time_out'],
                    $groups[$key]['log_time_in'],
                    $groups[$key]['log_time_out'],
                    $groups[$key]['day_log_time_in'],
                    $groups[$key]['day_log_time_out'],
                );
                $groups[$key]['_codes'] = [];
                $groups[$key]['_names'] = [];
                $groups[$key]['_sections'] = [];
                $groups[$key]['_rooms'] = [];
            } else {
                $status = strtolower(trim((string) ($log['status'] ?? '')));

                if ($status !== '' && $status !== 'scheduled') {
                    $groups[$key]['status'] = $log['status'] ?? $groups[$key]['status'] ?? null;
                    $groups[$key]['status_code'] = $log['status_code'] ?? $groups[$key]['status_code'] ?? null;
                    $groups[$key]['present_mode'] = $log['present_mode'] ?? $groups[$key]['present_mode'] ?? null;
                    $groups[$key]['status_label'] = $log['status_label'] ?? $groups[$key]['status_label'] ?? null;
                }
            }

            self::pushUnique($groups[$key]['_codes'], $log['subject_code'] ?? null);
            self::pushUnique($groups[$key]['_names'], $log['subject_name'] ?? null);
            self::pushUnique($groups[$key]['_sections'], $log['section'] ?? null);
            self::pushUnique($groups[$key]['_rooms'], $log['room'] ?? null);
        }

        $rows = [];

        foreach ($groups as $group) {
            $codes = $group['_codes'];
            $names = $group['_names'];
            $sections = $group['_sections'];
            $rooms = $group['_rooms'];
            unset($group['_codes'], $group['_names'], $group['_sections'], $group['_rooms']);

            $group['subject_code'] = $codes !== [] ? implode(', ', $codes) : null;
            $group['subject_name'] = $names !== [] ? implode(', ', $names) : null;
            $group['section'] = $sections !== [] ? implode(', ', $sections) : null;
            $group['room'] = $rooms !== [] ? implode(', ', $rooms) : ($group['room'] ?? null);

            $hours = self::hoursBetween($group['time_in'] ?? null, $group['time_out'] ?? null);

            if ($hours !== null) {
                $group['total_hours'] = $hours;
            }

            if (! isset($group['schedule']) || trim((string) $group['schedule']) === '') {
                $clock = trim((string) ($group['time_in'] ?? '')).(($group['time_out'] ?? '') !== '' && ($group['time_out'] ?? null) !== null
                    ? ' - '.$group['time_out']
                    : '');
                $group['schedule'] = trim(trim((string) ($group['schedule_day'] ?? '')).' '.$clock);
            }

            $rows[] = $group;
        }

        return $rows;
    }

    /**
     * @param  list<string>  $values
     */
    private static function pushUnique(array &$values, mixed $value): void
    {
        $text = trim((string) $value);

        if ($text === '' || in_array($text, $values, true)) {
            return;
        }

        $values[] = $text;
    }

    private static function hoursBetween(mixed $timeIn, mixed $timeOut): ?float
    {
        $start = self::minutes($timeIn);
        $end = self::minutes($timeOut);

        if ($start === null || $end === null) {
            return null;
        }

        $diff = $end - $start;

        if ($diff < 0) {
            $diff += 24 * 60;
        }

        return round($diff / 60, 2);
    }

    private static function minutes(mixed $value): ?int
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        try {
            $clock = CarbonImmutable::parse($text);

            return ((int) $clock->format('H')) * 60 + ((int) $clock->format('i'));
        } catch (\Throwable) {
            return null;
        }
    }
}
