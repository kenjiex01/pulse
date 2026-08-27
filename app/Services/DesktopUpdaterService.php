<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Native\Laravel\Facades\AutoUpdater;

class DesktopUpdaterService
{
    public const CACHE_PENDING = 'desktop.updater.pending';

    public const CACHE_DOWNLOADING = 'desktop.updater.downloading';

    public const CACHE_INSTALLING = 'desktop.updater.installing';

    public function enabled(): bool
    {
        if (! (bool) config('nativephp.updater.enabled', false)) {
            return false;
        }

        return (bool) config('nativephp-internal.running', env('NATIVEPHP_RUNNING', false));
    }

    /**
     * @return array{enabled: bool, force_install: bool, version: string, pending: array{version: string, release_name: string|null}|null, downloading: array{version: string, percent: float}|null, installing: array{version: string, percent: float}|null}
     */
    public function status(): array
    {
        $this->pruneStaleUpdateState();

        return [
            'enabled' => $this->enabled(),
            'force_install' => $this->forceInstallWhenReady(),
            'version' => $this->currentVersion(),
            'pending' => Cache::get(self::CACHE_PENDING),
            'downloading' => Cache::get(self::CACHE_DOWNLOADING),
            'installing' => Cache::get(self::CACHE_INSTALLING),
        ];
    }

    public function checkForUpdates(): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->pruneStaleUpdateState();

        try {
            AutoUpdater::checkForUpdates();
        } catch (\Throwable) {
            // Browser / non-native runtime — ignore.
        }
    }

    public function quitAndInstall(): void
    {
        if (! $this->enabled()) {
            return;
        }

        $pending = Cache::get(self::CACHE_PENDING);
        $version = is_array($pending) ? (string) ($pending['version'] ?? '') : '';
        $this->markInstalling($version !== '' ? $version : null);

        AutoUpdater::quitAndInstall();
    }

    public function downloadUpdateSafely(): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            AutoUpdater::downloadUpdate();
        } catch (\Throwable) {
            // Already downloading, or browser / non-native runtime.
        }
    }

    public function markAvailable(string $version): void
    {
        if (! $this->isNewerThanCurrent($version)) {
            Cache::forget(self::CACHE_DOWNLOADING);

            return;
        }

        Cache::forget(self::CACHE_INSTALLING);

        Cache::put(self::CACHE_DOWNLOADING, [
            'version' => $version,
            'percent' => 0.0,
        ], now()->addDay());

        $this->downloadUpdateSafely();
    }

    public function markProgress(float $percent, ?string $version = null): void
    {
        $current = Cache::get(self::CACHE_DOWNLOADING, []);
        $resolvedVersion = $version ?? ($current['version'] ?? null);

        if (is_string($resolvedVersion) && ! $this->isNewerThanCurrent($resolvedVersion)) {
            Cache::forget(self::CACHE_DOWNLOADING);

            return;
        }

        Cache::put(self::CACHE_DOWNLOADING, [
            'version' => $resolvedVersion,
            'percent' => max(0, min(100, $percent)),
        ], now()->addDay());
    }

    public function markDownloaded(string $version, ?string $releaseName = null): void
    {
        Cache::forget(self::CACHE_DOWNLOADING);

        if (! $this->isNewerThanCurrent($version)) {
            Cache::forget(self::CACHE_PENDING);
            Cache::forget(self::CACHE_INSTALLING);

            return;
        }

        Cache::put(self::CACHE_PENDING, [
            'version' => $version,
            'release_name' => $releaseName,
        ], now()->addDays(7));

        if ($this->forceInstallWhenReady()) {
            $this->quitAndInstallSafely();
        }
    }

    public function markInstalling(?string $version = null): void
    {
        if ($version === null || $version === '') {
            $pending = Cache::get(self::CACHE_PENDING);
            $version = is_array($pending) ? (string) ($pending['version'] ?? '') : '';
        }

        Cache::forget(self::CACHE_DOWNLOADING);

        Cache::put(self::CACHE_INSTALLING, [
            'version' => $version !== '' ? $version : null,
            'percent' => 100.0,
            'started_at' => now()->timestamp,
        ], now()->addHour());
    }

    public function forceInstallWhenReady(): bool
    {
        return (bool) config('desktop_updater.force_install', true);
    }

    public function quitAndInstallSafely(): void
    {
        try {
            $this->quitAndInstall();
        } catch (\Throwable) {
            // Browser / non-native runtime — ignore.
        }
    }

    public function clear(): void
    {
        Cache::forget(self::CACHE_PENDING);
        Cache::forget(self::CACHE_DOWNLOADING);
        Cache::forget(self::CACHE_INSTALLING);
    }

    public function currentVersion(): string
    {
        return $this->normalizeVersion((string) config('nativephp.version', '0.0.0'));
    }

    public function isNewerThanCurrent(string $candidate): bool
    {
        return version_compare($this->normalizeVersion($candidate), $this->currentVersion(), '>');
    }

    private function pruneStaleUpdateState(): void
    {
        $pending = Cache::get(self::CACHE_PENDING);
        if (is_array($pending) && isset($pending['version']) && ! $this->isNewerThanCurrent((string) $pending['version'])) {
            Cache::forget(self::CACHE_PENDING);
        }

        $downloading = Cache::get(self::CACHE_DOWNLOADING);
        if (is_array($downloading) && isset($downloading['version']) && ! $this->isNewerThanCurrent((string) $downloading['version'])) {
            Cache::forget(self::CACHE_DOWNLOADING);
        }

        $installing = Cache::get(self::CACHE_INSTALLING);
        if (is_array($installing) && isset($installing['version']) && is_string($installing['version']) && $installing['version'] !== ''
            && ! $this->isNewerThanCurrent((string) $installing['version'])) {
            Cache::forget(self::CACHE_INSTALLING);
        }
    }

    private function normalizeVersion(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }
}
