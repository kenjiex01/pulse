<?php

namespace App\Services;

use App\Support\DesktopConnectivity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DesktopInstallerUpdateService
{
    private const CACHE_KEY = 'desktop.installer.update.check';

    public function isEnabled(): bool
    {
        if (! (bool) config('desktop_updater.enabled', true)) {
            return false;
        }

        if (! $this->isNativeDesktop()) {
            return false;
        }

        $bucket = trim((string) config('backup.cloud.bucket', ''));
        $key = trim((string) config('backup.cloud.key', ''));
        $secret = trim((string) config('backup.cloud.secret', ''));

        return $bucket !== '' && $key !== '' && $secret !== '';
    }

    public function isNativeDesktop(): bool
    {
        return (bool) config('nativephp-internal.running', env('NATIVEPHP_RUNNING', false));
    }

    public function currentVersion(): string
    {
        return $this->normalizeVersion((string) config('nativephp.version', '0.0.0'));
    }

    /**
     * Platform key used for artifact matching (win-x64 | mac-arm64 | mac-x64).
     */
    public function platformKey(): string
    {
        $family = PHP_OS_FAMILY;

        if ($family === 'Windows') {
            return 'win-x64';
        }

        if ($family === 'Darwin') {
            $machine = strtolower(php_uname('m'));

            if (str_contains($machine, 'arm') || str_contains($machine, 'aarch')) {
                return 'mac-arm64';
            }

            return 'mac-x64';
        }

        return 'win-x64';
    }

    /**
     * Cached check for a newer installer on S3.
     *
     * @return array{
     *     available: bool,
     *     current_version: string,
     *     latest_version: string|null,
     *     platform: string,
     *     filename: string|null,
     *     s3_key: string|null,
     *     checked_at: string|null,
     *     error: string|null
     * }|null
     */
    public function checkIfNeeded(bool $force = false): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        if (! app(DesktopConnectivity::class)->isOnline()) {
            return [
                'available' => false,
                'current_version' => $this->currentVersion(),
                'latest_version' => null,
                'platform' => $this->platformKey(),
                'filename' => null,
                's3_key' => null,
                'checked_at' => now()->toIso8601String(),
                'error' => 'offline',
            ];
        }

        $ttlMinutes = (int) config('desktop_updater.check_interval_minutes', 0);

        if ($force || $ttlMinutes <= 0) {
            Cache::forget(self::CACHE_KEY);
        }

        try {
            if ($ttlMinutes <= 0) {
                return $this->discoverLatest();
            }

            return Cache::remember(self::CACHE_KEY, now()->addMinutes($ttlMinutes), function () {
                return $this->discoverLatest();
            });
        } catch (Throwable $exception) {
            Log::warning('Desktop installer update check failed.', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'available' => false,
                'current_version' => $this->currentVersion(),
                'latest_version' => null,
                'platform' => $this->platformKey(),
                'filename' => null,
                's3_key' => null,
                'checked_at' => now()->toIso8601String(),
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Payload for the update modal, or null when nothing should be shown.
     *
     * @return array{
     *     current_version: string,
     *     latest_version: string,
     *     platform: string,
     *     filename: string,
     *     download_url: string
     * }|null
     */
    public function pendingUpdateForUi(): ?array
    {
        $check = $this->checkIfNeeded();

        if ($check === null || ! ($check['available'] ?? false)) {
            return null;
        }

        $latest = (string) ($check['latest_version'] ?? '');
        $s3Key = (string) ($check['s3_key'] ?? '');
        $filename = (string) ($check['filename'] ?? '');

        if ($latest === '' || $s3Key === '' || $filename === '') {
            return null;
        }

        return [
            'current_version' => (string) $check['current_version'],
            'latest_version' => $latest,
            'platform' => (string) $check['platform'],
            'filename' => $filename,
            'download_url' => route('desktop.update.download'),
        ];
    }

    /**
     * Fresh pre-signed URL for the latest installer for this platform.
     */
    public function temporaryDownloadUrl(): ?string
    {
        $check = $this->checkIfNeeded();

        if ($check === null || ! ($check['available'] ?? false) || empty($check['s3_key'])) {
            return null;
        }

        $minutes = max(5, (int) config('desktop_updater.download_url_minutes', 60));

        try {
            return Storage::disk((string) config('desktop_updater.disk', 'backup-s3'))
                ->temporaryUrl(
                    (string) $check['s3_key'],
                    now()->addMinutes($minutes),
                    [
                        'ResponseContentDisposition' => 'attachment; filename="'.addslashes((string) $check['filename']).'"',
                    ],
                );
        } catch (Throwable $exception) {
            Log::error('Desktop installer pre-signed URL failed.', [
                'message' => $exception->getMessage(),
                's3_key' => $check['s3_key'] ?? null,
            ]);

            return null;
        }
    }

    /**
     * Upload local installers for a version and write latest.json.
     * Keeps only this version's artifacts under the prefix (deletes older Pulse installers).
     *
     * @param  list<string>  $paths Absolute paths to dmg/exe files
     * @return array{
     *     uploaded: list<string>,
     *     deleted: list<string>,
     *     latest_key: string,
     *     version: string,
     *     artifacts: array<string, array{key: string, filename: string, bytes: int|null}>,
     *     dry_run: bool
     * }
     */
    public function uploadInstallers(string $version, array $paths, bool $dryRun = false): array
    {
        $version = $this->normalizeVersion($version);
        $prefix = trim((string) config('desktop_updater.s3_prefix', 'payroll_installer'), '/');
        $disk = Storage::disk((string) config('desktop_updater.disk', 'backup-s3'));
        $uploaded = [];
        $artifacts = [];

        foreach ($paths as $path) {
            if (! is_string($path) || $path === '' || ! is_file($path)) {
                continue;
            }

            $localFilename = basename($path);
            $platform = $this->platformForFilename($localFilename);

            if ($platform === null) {
                continue;
            }

            $downloadFilename = $this->versionedDownloadFilename($platform, $version, $localFilename);
            $key = $prefix === '' ? $downloadFilename : $prefix.'/'.$downloadFilename;

            if (! $dryRun) {
                $attempts = 0;
                $lastError = null;

                while ($attempts < 3) {
                    $attempts++;
                    $stream = fopen($path, 'rb');
                    if ($stream === false) {
                        throw new \RuntimeException('Unable to read installer: '.$path);
                    }

                    try {
                        $ok = $disk->put($key, $stream, ['visibility' => 'private']);
                        if ($ok) {
                            $lastError = null;
                            break;
                        }
                        $lastError = new \RuntimeException('S3 upload returned false for '.$key);
                    } catch (Throwable $exception) {
                        $lastError = $exception;
                        Log::warning('Desktop installer S3 upload attempt failed', [
                            'key' => $key,
                            'attempt' => $attempts,
                            'message' => $exception->getMessage(),
                        ]);
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }

                    if ($attempts < 3) {
                        sleep(5 * $attempts);
                    }
                }

                if ($lastError !== null) {
                    throw $lastError instanceof \RuntimeException
                        ? $lastError
                        : new \RuntimeException('S3 upload failed for '.$key.': '.$lastError->getMessage(), 0, $lastError);
                }
            }

            $uploaded[] = $key;
            $artifacts[$platform] = [
                'key' => $key,
                'filename' => $downloadFilename,
                'bytes' => filesize($path) ?: null,
            ];
        }

        if ($artifacts === []) {
            throw new \RuntimeException('No matching Pulse installers found to upload.');
        }

        $manifest = [
            'version' => $version,
            'published_at' => now()->toIso8601String(),
            'artifacts' => $artifacts,
        ];

        $latestKey = $prefix === '' ? 'latest.json' : $prefix.'/latest.json';
        $keepKeys = array_fill_keys([...$uploaded, $latestKey], true);

        if (! $dryRun) {
            $disk->put(
                $latestKey,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ['visibility' => 'private', 'ContentType' => 'application/json'],
            );
        }

        // After a successful upload, keep only this version's installers + latest.json.
        $deleted = $this->deleteStaleInstallers($disk, $prefix, $keepKeys, $dryRun);

        return [
            'uploaded' => $uploaded,
            'deleted' => $deleted,
            'latest_key' => $latestKey,
            'version' => $version,
            'artifacts' => $artifacts,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * Remove every other object under the installer prefix so S3 keeps only the
     * version that was just uploaded (plus latest.json).
     *
     * @param  \Illuminate\Contracts\Filesystem\Filesystem  $disk
     * @param  array<string, true>  $keepKeys
     * @return list<string>
     */
    private function deleteStaleInstallers($disk, string $prefix, array $keepKeys, bool $dryRun): array
    {
        $deleted = [];
        $failed = [];
        $directory = $prefix === '' ? null : $prefix;
        $files = method_exists($disk, 'allFiles')
            ? $disk->allFiles($directory)
            : $disk->files($directory);

        foreach ($files as $key) {
            $key = ltrim(str_replace('\\', '/', (string) $key), '/');

            if ($key === '' || isset($keepKeys[$key])) {
                continue;
            }

            // Never delete latest.json here — it was just rewritten for the new version.
            if (strcasecmp(basename($key), 'latest.json') === 0) {
                continue;
            }

            if ($dryRun) {
                $deleted[] = $key;

                continue;
            }

            try {
                if (! $disk->delete($key)) {
                    $failed[] = $key;

                    continue;
                }

                $deleted[] = $key;
            } catch (Throwable $exception) {
                Log::warning('Failed to delete stale desktop installer from S3.', [
                    's3_key' => $key,
                    'message' => $exception->getMessage(),
                ]);
                $failed[] = $key;
            }
        }

        sort($deleted);

        if ($failed !== []) {
            throw new \RuntimeException(
                'Uploaded the new installers, but failed to delete older objects from S3 '
                .'(IAM needs s3:DeleteObject on '.$prefix.'/*): '
                .implode(', ', $failed)
            );
        }

        return $deleted;
    }

    private function versionedDownloadFilename(string $platform, string $version, string $fallback): string
    {
        return match ($platform) {
            'win-x64' => 'Pulse-'.$version.'-setup.exe',
            'mac-arm64' => 'Pulse-'.$version.'-arm64.dmg',
            'mac-x64' => 'Pulse-'.$version.'-x64.dmg',
            default => $fallback,
        };
    }

    /**
     * @return array{
     *     available: bool,
     *     current_version: string,
     *     latest_version: string|null,
     *     platform: string,
     *     filename: string|null,
     *     s3_key: string|null,
     *     checked_at: string|null,
     *     error: string|null
     * }
     */
    private function discoverLatest(): array
    {
        $current = $this->currentVersion();
        $platform = $this->platformKey();
        $empty = [
            'available' => false,
            'current_version' => $current,
            'latest_version' => null,
            'platform' => $platform,
            'filename' => null,
            's3_key' => null,
            'checked_at' => now()->toIso8601String(),
            'error' => null,
        ];

        $fromManifest = $this->latestFromManifest($platform);

        if ($fromManifest !== null) {
            return $this->compareResult($current, $fromManifest['version'], $platform, $fromManifest['filename'], $fromManifest['key']);
        }

        $fromListing = $this->latestFromListing($platform);

        if ($fromListing === null) {
            return $empty;
        }

        return $this->compareResult(
            $current,
            $fromListing['version'],
            $platform,
            $fromListing['filename'],
            $fromListing['key'],
        );
    }

    /**
     * @return array{version: string, filename: string, key: string}|null
     */
    private function latestFromManifest(string $platform): ?array
    {
        $prefix = trim((string) config('desktop_updater.s3_prefix', 'payroll_installer'), '/');
        $key = $prefix === '' ? 'latest.json' : $prefix.'/latest.json';
        $disk = Storage::disk((string) config('desktop_updater.disk', 'backup-s3'));

        if (! $disk->exists($key)) {
            return null;
        }

        $decoded = json_decode((string) $disk->get($key), true);

        if (! is_array($decoded)) {
            return null;
        }

        $version = $this->normalizeVersion((string) ($decoded['version'] ?? ''));
        $artifact = $decoded['artifacts'][$platform] ?? null;

        if ($version === '' || ! is_array($artifact)) {
            return null;
        }

        $artifactKey = (string) ($artifact['key'] ?? '');
        $filename = (string) ($artifact['filename'] ?? basename($artifactKey));

        if ($artifactKey === '' || $filename === '') {
            return null;
        }

        return [
            'version' => $version,
            'filename' => $filename,
            'key' => $artifactKey,
        ];
    }

    /**
     * @return array{version: string, filename: string, key: string}|null
     */
    private function latestFromListing(string $platform): ?array
    {
        $patterns = config('desktop_updater.artifacts', []);
        $pattern = $patterns[$platform] ?? null;

        if (! is_string($pattern) || $pattern === '') {
            return null;
        }

        $prefix = trim((string) config('desktop_updater.s3_prefix', 'payroll_installer'), '/');
        $disk = Storage::disk((string) config('desktop_updater.disk', 'backup-s3'));
        $files = $disk->files($prefix === '' ? null : $prefix);

        $best = null;
        $bestVersion = null;

        foreach ($files as $key) {
            $filename = basename((string) $key);

            if (preg_match($pattern, $filename, $matches) !== 1) {
                continue;
            }

            $version = $this->normalizeVersion((string) ($matches[1] ?? ''));

            if ($version === '') {
                continue;
            }

            if ($bestVersion === null || version_compare($version, $bestVersion, '>')) {
                $bestVersion = $version;
                $best = [
                    'version' => $version,
                    'filename' => $filename,
                    'key' => (string) $key,
                ];
            }
        }

        return $best;
    }

    /**
     * @return array{
     *     available: bool,
     *     current_version: string,
     *     latest_version: string|null,
     *     platform: string,
     *     filename: string|null,
     *     s3_key: string|null,
     *     checked_at: string|null,
     *     error: string|null
     * }
     */
    private function compareResult(
        string $current,
        string $latest,
        string $platform,
        string $filename,
        string $key,
    ): array {
        $available = version_compare($latest, $current, '>');

        return [
            'available' => $available,
            'current_version' => $current,
            'latest_version' => $latest,
            'platform' => $platform,
            'filename' => $filename,
            's3_key' => $key,
            'checked_at' => now()->toIso8601String(),
            'error' => null,
        ];
    }

    private function platformForFilename(string $filename): ?string
    {
        foreach (config('desktop_updater.artifacts', []) as $platform => $pattern) {
            if (is_string($pattern) && preg_match($pattern, $filename) === 1) {
                return (string) $platform;
            }
        }

        return null;
    }

    private function normalizeVersion(string $version): string
    {
        $version = trim($version);
        $version = ltrim($version, 'vV');

        return $version;
    }
}
