<?php

namespace App\Support;

/**
 * Skolaris Attendance Checker marks on teaching loads.
 *
 * When the checker has not set a mark (empty or "scheduled"), ICCT treats the session as Present
 * on employee loads — schedule times apply for Time In/Out unless a explicit absent/late mark exists.
 */
final class SkolarisCheckerLoadStatus
{
    public static function isUnmarked(mixed $status): bool
    {
        $normalized = trim((string) $status);

        return $normalized === '' || strcasecmp($normalized, 'scheduled') === 0;
    }

    /**
     * Status stored on teaching load / employee load rows after checker merge or pull.
     */
    public static function statusCodeFromChecker(mixed $status, mixed $presentMode = null): string
    {
        $status = trim((string) $status);
        $presentMode = trim((string) ($presentMode ?? ''));

        if (self::isUnmarked($status)) {
            return 'P';
        }

        if ($status === 'P' && $presentMode !== '') {
            return 'P ('.$presentMode.')';
        }

        return $status;
    }

    public static function isExplicitlyAbsent(mixed $status): bool
    {
        $normalized = strtoupper(trim((string) $status));

        if ($normalized === '') {
            return false;
        }

        if (in_array($normalized, ['A', 'ABS', 'ABSENT', 'UA', 'UNAUTHORIZED'], true)) {
            return true;
        }

        return str_contains($normalized, 'ABSENT');
    }

    /**
     * Present for memo/payroll attendance — only explicit absent marks count as not present.
     */
    public static function countsAsPresent(mixed $status): bool
    {
        return ! self::isExplicitlyAbsent($status);
    }
}
