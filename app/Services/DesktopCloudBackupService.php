<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DesktopCloudBackupService
{
    private const DATE_MARKER = 'app/.desktop-cloud-backup-date';

    private static bool $dispatchQueued = false;

    public function backupIfNeeded(): void
    {
        if (! $this->isEnabled() || self::$dispatchQueued) {
            return;
        }

        if (! $this->shouldRunNow()) {
            return;
        }

        self::$dispatchQueued = true;

        dispatch(function (): void {
            $this->runBackupAndUpload();
        })->afterResponse();
    }

    public function shouldRunNow(?Carbon $now = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $now ??= $this->now();
        $scheduledAt = $this->scheduledTimeFor($now);

        if ($now->lt($scheduledAt)) {
            return false;
        }

        return ! $this->hasUploadedToday($now);
    }

    public function runBackupAndUpload(bool $force = false): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $now = $this->now();

        if (! $force && ! $this->shouldRunNow($now)) {
            return null;
        }

        $backupService = app(DatabaseBackupService::class);
        $backup = null;
        $s3Key = null;

        try {
            $backup = $backupService->createGzipped();
            $cloudFilename = $this->cloudFilename($backup['gz_filename']);
            $s3Key = $this->uploadPath($cloudFilename);

            $uploaded = Storage::disk($this->disk())->put(
                $s3Key,
                File::get($backup['gz_path']),
                ['visibility' => 'private'],
            );

            if (! $uploaded) {
                throw new \RuntimeException('S3 upload returned false.');
            }

            $this->markUploadedToday($now, $s3Key);
            $backupService->pruneOldCopies();

            if (! config('backup.cloud.keep_local_gzip', false)) {
                File::delete($backup['gz_path']);
                File::delete($backup['path']);
            }

            SysLogService::record(
                action: 'add',
                table: 'database_backup',
                description: 'Uploaded daily database backup to cloud ('.$cloudFilename.', '.number_format($backup['gz_size']).' bytes)',
            );

            Log::info('Desktop cloud database backup uploaded.', [
                's3_key' => $s3Key,
                'desktop_name' => $this->desktopName(),
                'size' => $backup['gz_size'],
            ]);

            return [
                's3_key' => $s3Key,
                'filename' => $cloudFilename,
                'size' => $backup['gz_size'],
            ];
        } catch (Throwable $exception) {
            Log::error('Desktop cloud database backup failed.', [
                'message' => $exception->getMessage(),
                's3_key' => $s3Key,
            ]);

            report($exception);

            return null;
        }
    }

    public function hasUploadedToday(?Carbon $now = null): bool
    {
        $markerPath = $this->markerPath();

        if (! File::exists($markerPath)) {
            return false;
        }

        $storedDate = trim((string) File::get($markerPath));
        $now ??= $this->now();

        return $storedDate === $now->toDateString();
    }

    public function clearTodayMarker(): bool
    {
        $markerPath = $this->markerPath();
        $cleared = false;

        if (File::exists($markerPath) && $this->hasUploadedToday()) {
            File::delete($markerPath);
            $cleared = true;
        }

        $logPath = storage_path('app/.desktop-cloud-backup-last.json');

        if (File::exists($logPath)) {
            $last = json_decode((string) File::get($logPath), true);

            if (is_array($last) && ($last['date'] ?? null) === $this->now()->toDateString()) {
                File::delete($logPath);
                $cleared = true;
            }
        }

        return $cleared;
    }

    public function uploadPath(string $filename): string
    {
        $prefix = trim((string) config('backup.cloud.s3_prefix', 'payroll-backups'), '/');

        return $prefix === '' ? $filename : $prefix.'/'.$filename;
    }

    /**
     * Build the S3 object filename, including this machine's desktop/host name.
     *
     * Example: pulse-db-Kents-MacBook-Pro-2026-07-23-10-00-00.sql.gz
     */
    public function cloudFilename(string $gzFilename): string
    {
        $desktop = $this->desktopName();

        if (preg_match('/^pulse-db-(.+)$/', $gzFilename, $matches) === 1) {
            return 'pulse-db-'.$desktop.'-'.$matches[1];
        }

        return $desktop.'-'.$gzFilename;
    }

    /**
     * Sanitized computer/host name for use in S3 object keys.
     */
    public function desktopName(): string
    {
        $name = gethostname() ?: php_uname('n') ?: 'desktop';
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $name) ?? 'desktop';
        $name = trim($name, '.-_');

        if ($name === '') {
            return 'desktop';
        }

        // Keep S3 keys readable and within common object-key length limits.
        return substr($name, 0, 64);
    }

    /**
     * @return array{
     *     enabled: bool,
     *     configured: bool,
     *     schedule_label: string,
     *     last_uploaded_at: ?string,
     *     last_s3_key: ?string,
     *     uploaded_today: bool,
     *     ready_to_run: bool,
     * }
     */
    public function status(): array
    {
        $logPath = storage_path('app/.desktop-cloud-backup-last.json');
        $last = File::exists($logPath)
            ? json_decode((string) File::get($logPath), true)
            : null;

        $hour = (int) config('backup.cloud.schedule_hour', 10);
        $minute = (int) config('backup.cloud.schedule_minute', 0);
        $now = $this->now();
        $configured = $this->isEnabled();

        return [
            'enabled' => (bool) config('backup.cloud.enabled', false),
            'configured' => $configured,
            'schedule_label' => sprintf('%02d:%02d %s', $hour, $minute, config('backup.cloud.timezone', 'Asia/Manila')),
            'last_uploaded_at' => is_array($last) ? ($last['uploaded_at'] ?? null) : null,
            'last_s3_key' => is_array($last) ? ($last['s3_key'] ?? null) : null,
            'uploaded_today' => $this->hasUploadedToday($now),
            'ready_to_run' => $configured && $this->shouldRunNow($now),
        ];
    }

    private function markUploadedToday(Carbon $now, string $s3Key): void
    {
        $markerPath = $this->markerPath();
        File::ensureDirectoryExists(dirname($markerPath));
        File::put($markerPath, $now->toDateString());

        $logPath = storage_path('app/.desktop-cloud-backup-last.json');
        File::put($logPath, json_encode([
            'date' => $now->toDateString(),
            'uploaded_at' => $now->toIso8601String(),
            's3_key' => $s3Key,
        ], JSON_PRETTY_PRINT));
    }

    private function scheduledTimeFor(Carbon $now): Carbon
    {
        return $now->copy()->setTime(
            (int) config('backup.cloud.schedule_hour', 10),
            (int) config('backup.cloud.schedule_minute', 0),
            0,
        );
    }

    private function now(): Carbon
    {
        return now((string) config('backup.cloud.timezone', 'Asia/Manila'));
    }

    private function markerPath(): string
    {
        return storage_path(self::DATE_MARKER);
    }

    private function disk(): string
    {
        return (string) config('backup.cloud.disk', 'backup-s3');
    }

    private function isEnabled(): bool
    {
        return (bool) config('backup.cloud.enabled', false)
            && filled(config('backup.cloud.bucket'))
            && filled(config('backup.cloud.key'))
            && filled(config('backup.cloud.secret'));
    }
}
