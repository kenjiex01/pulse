<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Fast online check for desktop cloud work (S3 backup, installer updates).
 * Core Pulse never needs this — payroll, timekeeping, and SQLite stay local.
 */
final class DesktopConnectivity
{
    private const CACHE_FILE = 'app/.desktop-connectivity.json';

    private const ONLINE_TTL_SECONDS = 30;

    private const OFFLINE_TTL_SECONDS = 90;

    private static ?bool $forced = null;

    private static ?bool $memo = null;

    public static function fake(?bool $online): void
    {
        self::$forced = $online;
        self::$memo = null;
    }

    public function isOnline(): bool
    {
        if (self::$forced !== null) {
            return self::$forced;
        }

        if (self::$memo !== null) {
            return self::$memo;
        }

        $cached = $this->readCache();

        if ($cached !== null) {
            return self::$memo = $cached;
        }

        $online = $this->probe();
        $this->writeCache($online);

        return self::$memo = $online;
    }

    private function probe(): bool
    {
        $region = trim((string) config('filesystems.disks.backup-s3.region', 'ap-southeast-2'));

        if ($region === '') {
            $region = 'ap-southeast-2';
        }

        $hosts = [
            's3.'.$region.'.amazonaws.com',
            '1.1.1.1',
        ];

        foreach ($hosts as $host) {
            $errno = 0;
            $errstr = '';
            $socket = @fsockopen($host, 443, $errno, $errstr, 1.0);

            if (is_resource($socket)) {
                fclose($socket);

                return true;
            }
        }

        return false;
    }

    private function readCache(): ?bool
    {
        $path = storage_path(self::CACHE_FILE);

        if (! File::exists($path)) {
            return null;
        }

        $decoded = json_decode((string) File::get($path), true);

        if (! is_array($decoded) || ! array_key_exists('online', $decoded) || ! isset($decoded['checked_at'])) {
            return null;
        }

        $checkedAt = (int) $decoded['checked_at'];
        $online = (bool) $decoded['online'];
        $ttl = $online ? self::ONLINE_TTL_SECONDS : self::OFFLINE_TTL_SECONDS;

        if ($checkedAt < 1 || (time() - $checkedAt) > $ttl) {
            return null;
        }

        return $online;
    }

    private function writeCache(bool $online): void
    {
        $path = storage_path(self::CACHE_FILE);

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'online' => $online,
            'checked_at' => time(),
        ]));
    }
}
