<?php

namespace App\Support;

class PhpIniSize
{
    public static function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));

        if (in_array($unit, ['g', 'm', 'k'], true)) {
            $number = (float) substr($value, 0, -1);
        } else {
            $number = (float) $value;
            $unit = '';
        }

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public static function postMaxBytes(): int
    {
        return self::toBytes((string) ini_get('post_max_size'));
    }

    public static function uploadMaxBytes(): int
    {
        return self::toBytes((string) ini_get('upload_max_filesize'));
    }

    /**
     * Smallest of Laravel config limit and current PHP upload/post ini (kilobytes).
     */
    public static function effectiveSqlRestoreMaxKb(): int
    {
        $configured = max(1, (int) config('uploads.sql_restore_max_kb', 262144));
        $phpMaxBytes = min(self::postMaxBytes(), self::uploadMaxBytes());

        if ($phpMaxBytes <= 0) {
            return $configured;
        }

        $phpMaxKb = max(1, (int) floor($phpMaxBytes / 1024));

        return min($configured, $phpMaxKb);
    }

    public static function sqlRestoreLimitedByPhpIni(): bool
    {
        return self::effectiveSqlRestoreMaxKb() < (int) config('uploads.sql_restore_max_kb', 262144);
    }
}
