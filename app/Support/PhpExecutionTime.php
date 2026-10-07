<?php

namespace App\Support;

/**
 * Raise PHP script time limits for long HTTP requests (Skolaris pull, reports, etc.).
 *
 * Bundled/desktop PHP is often started with -d max_execution_time=0, which some runtimes
 * treat as an immediate 0-second cap (not unlimited). Always set an explicit positive limit.
 */
class PhpExecutionTime
{
    public static function ensureAtLeast(int $seconds): void
    {
        if ($seconds < 1) {
            return;
        }

        $current = (int) ini_get('max_execution_time');

        if ($current === 0 || ($current > 0 && $current < $seconds)) {
            @ini_set('max_execution_time', (string) $seconds);
        }

        @set_time_limit($seconds);
    }
}
