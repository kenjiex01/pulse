<?php

namespace App\Support;

class DesktopStartupProbe
{
    /**
     * Electron runs these before the window exists. They only print JSON.
     * Skipping database setup here avoids extra SQLite boots on every launch.
     */
    public static function isRunning(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        $command = implode(' ', $_SERVER['argv'] ?? []);

        return str_contains($command, 'native:config')
            || str_contains($command, 'native:php-ini');
    }
}
