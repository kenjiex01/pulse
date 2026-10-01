<?php

namespace App\Support;

use App\Services\EmployeeLoadPayrollService;
use Carbon\CarbonImmutable;

/**
 * Resolve Time In / Time Out for Employee Load from Skolaris daily-load rows.
 *
 * Skolaris Loading Attendance often marks Present without per-row biometric logs;
 * in that case the session schedule on the load is the intended in/out for payroll upload.
 */
final class SkolarisLoadSessionTimes
{
    /**
     * @param  array<string, mixed>  $load
     * @return array{time_in: string, time_out: string}
     */
    public static function forTemplateCsv(array $load): array
    {
        [$in, $out] = self::resolveClockPair($load);

        return [
            'time_in' => self::formatTemplateTime($in),
            'time_out' => self::formatTemplateTime($out),
        ];
    }

    /**
     * @param  array<string, mixed>  $load
     * @return array{time_in: ?string, time_out: ?string} H:i:s for database
     */
    public static function forEmployeeLoadEntry(array $load): array
    {
        [$in, $out] = self::resolveClockPair($load);

        return [
            'time_in' => self::formatStorageTime($in),
            'time_out' => self::formatStorageTime($out),
        ];
    }

    /**
     * @param  array<string, mixed>  $load
     * @return array{0: ?string, 1: ?string} 24h H:i:s
     */
    private static function resolveClockPair(array $load): array
    {
        $actualIn = self::normalizeClock($load['actual_time_in'] ?? null);
        $actualOut = self::normalizeClock($load['actual_time_out'] ?? null);

        if ($actualIn !== null || $actualOut !== null) {
            $scheduled = self::scheduledClockPair($load);

            return [
                $actualIn ?? $scheduled[0],
                $actualOut ?? $scheduled[1],
            ];
        }

        $logIn = self::normalizeClock($load['log_time_in'] ?? null);
        $logOut = self::normalizeClock($load['log_time_out'] ?? null);

        if ($logIn !== null || $logOut !== null) {
            $scheduled = self::scheduledClockPair($load);

            return [
                $logIn ?? $scheduled[0],
                $logOut ?? $scheduled[1],
            ];
        }

        return self::scheduledClockPair($load);
    }

    /**
     * @param  array<string, mixed>  $load
     * @return array{0: ?string, 1: ?string}
     */
    private static function scheduledClockPair(array $load): array
    {
        $in = self::normalizeClock($load['time_in'] ?? null)
            ?? self::normalizeClock($load['scheduled_time_in'] ?? null);
        $out = self::normalizeClock($load['time_out'] ?? null)
            ?? self::normalizeClock($load['scheduled_time_out'] ?? null);

        if ($in !== null && $out !== null) {
            return [$in, $out];
        }

        $schedule = trim((string) ($load['schedule'] ?? $load['class_schedule'] ?? ''));

        if ($schedule !== '') {
            $payroll = app(EmployeeLoadPayrollService::class);
            $in ??= $payroll->parseScheduleStart($schedule);
            $out ??= $payroll->parseScheduleEnd($schedule);
        }

        return [$in, $out];
    }

    private static function normalizeClock(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($text)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function formatTemplateTime(?string $clock): string
    {
        if ($clock === null || $clock === '') {
            return '';
        }

        try {
            return CarbonImmutable::createFromFormat('H:i:s', $clock)->format('g:i A');
        } catch (\Throwable) {
            return '';
        }
    }

    private static function formatStorageTime(?string $clock): ?string
    {
        if ($clock === null || $clock === '') {
            return null;
        }

        return $clock;
    }
}
