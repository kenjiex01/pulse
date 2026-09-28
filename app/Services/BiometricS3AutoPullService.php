<?php

namespace App\Services;

use App\Models\User;
use App\Support\DesktopConnectivity;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class BiometricS3AutoPullService
{
    private const LOCK_PATH = 'app/settings/biometric-s3-auto-pull.lock';

    private const LOCK_STALE_SECONDS = 900;

    public function __construct(
        private readonly BiometricLogsS3PullService $pullService,
        private readonly BiometricS3PullSettings $settings,
        private readonly DesktopConnectivity $connectivity,
    ) {}

    public function autoPullIfNeeded(?User $actor = null): void
    {
        if (app()->runningUnitTests() || ! $this->settings->shouldRunNow()) {
            return;
        }

        if (! $this->pullService->isConfigured() || $this->lockIsFresh()) {
            return;
        }

        $this->settings->markRunFinished();
        $this->spawnDetachedPull();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function runAutoPull(?User $actor = null): ?array
    {
        if (! $this->settings->isAutoPullEnabled() || ! $this->pullService->isConfigured()) {
            return null;
        }

        if (! $this->connectivity->isOnline()) {
            $this->settings->markRunFinished();

            return null;
        }

        if (! $this->acquireLock()) {
            return null;
        }

        try {
            return $this->pullRecentMonths($actor);
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function pullRecentMonths(?User $actor): array
    {
        $user = $this->resolveActor($actor);
        $now = now();
        $months = [
            [(int) $now->year, (int) $now->month],
            [(int) $now->copy()->subMonth()->year, (int) $now->copy()->subMonth()->month],
        ];

        $combined = [
            'files_scanned' => 0,
            'files_imported' => 0,
            'files_skipped' => 0,
            'files_already_pulled' => 0,
            'punches_inserted' => 0,
            'punches_skipped_duplicates' => 0,
            'punches_unmatched' => 0,
            'transactions' => [],
            'errors' => [],
        ];

        foreach ($months as [$year, $month]) {
            $summary = $this->pullService->pull(
                user: $user,
                year: $year,
                month: $month,
                campusId: null,
                collectorFolder: null,
                skipAlreadyPulled: true,
                autoPull: true,
            );

            foreach ($combined as $key => $value) {
                if ($key === 'transactions') {
                    $combined['transactions'] = array_merge($combined['transactions'], $summary['transactions'] ?? []);

                    continue;
                }

                if ($key === 'errors') {
                    $combined['errors'] = array_merge($combined['errors'], $summary['errors'] ?? []);

                    continue;
                }

                if (is_int($value)) {
                    $combined[$key] += (int) ($summary[$key] ?? 0);
                }
            }
        }

        $this->settings->markRunFinished();

        if (($combined['files_imported'] ?? 0) > 0) {
            SysLogService::record(
                action: 'add',
                table: 'raw_timekeeping_transactions',
                description: sprintf(
                    'Auto-pulled biometric S3 logs: %d new file(s), %d punch(es) inserted',
                    $combined['files_imported'],
                    $combined['punches_inserted'],
                ),
                userId: $user->id,
            );
        }

        if (($combined['errors'] ?? []) !== []) {
            Log::warning('Biometric S3 auto-pull finished with errors.', $combined);
        }

        return $combined;
    }

    private function resolveActor(?User $actor): User
    {
        if ($actor instanceof User) {
            return $actor;
        }

        $admin = User::query()->orderBy('id')->get()->first(fn (User $user) => $user->isAdmin());

        if ($admin instanceof User) {
            return $admin;
        }

        return User::query()->orderBy('id')->firstOrFail();
    }

    private function lockIsFresh(): bool
    {
        $path = storage_path(self::LOCK_PATH);

        if (! is_file($path)) {
            return false;
        }

        $started = (int) trim((string) File::get($path));

        return $started > 0 && (time() - $started) < self::LOCK_STALE_SECONDS;
    }

    private function acquireLock(): bool
    {
        $path = storage_path(self::LOCK_PATH);
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'c+');

        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            return false;
        }

        $started = (int) trim((string) stream_get_contents($handle));

        if ($started > 0 && (time() - $started) < self::LOCK_STALE_SECONDS) {
            flock($handle, LOCK_UN);
            fclose($handle);

            return false;
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) time());
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return true;
    }

    private function releaseLock(): void
    {
        $path = storage_path(self::LOCK_PATH);

        if (is_file($path)) {
            File::delete($path);
        }
    }

    private function spawnDetachedPull(): void
    {
        $log = storage_path('logs/biometric-s3-auto-pull.log');
        File::ensureDirectoryExists(dirname($log));

        $php = PHP_BINARY;

        if (PHP_OS_FAMILY === 'Windows') {
            $phpWin = dirname($php).DIRECTORY_SEPARATOR.'php-win.exe';

            if (is_file($phpWin)) {
                $php = $phpWin;
            }

            $inner = 'cd /d '.escapeshellarg(base_path())
                .' && '.escapeshellarg($php)
                .' '.escapeshellarg(base_path('artisan'))
                .' biometric:pull-s3 --auto >> '.escapeshellarg($log).' 2>&1';
            $vbs = storage_path('app/biometric-s3-auto-pull.vbs');
            File::put($vbs, "Set WshShell = CreateObject(\"WScript.Shell\")\r\n".
                'WshShell.Run "'.str_replace('"', '""', 'cmd /c '.$inner).'", 0, False'."\r\n");
            pclose(popen('wscript.exe //B //Nologo '.escapeshellarg($vbs), 'r'));

            return;
        }

        $process = Process::fromShellCommandline(
            sprintf(
                'nohup %s %s biometric:pull-s3 --auto >> %s 2>&1 &',
                escapeshellarg($php),
                escapeshellarg(base_path('artisan')),
                escapeshellarg($log),
            ),
            base_path(),
        );
        $process->setTimeout(null);
        $process->disableOutput();
        $process->start();
    }
}
