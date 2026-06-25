<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupService
{
    public function create(): array
    {
        $driver = DB::connection()->getDriverName();
        $timestamp = now()->format('Y-m-d_His');
        $backupDir = storage_path('app/backups');

        File::ensureDirectoryExists($backupDir);

        return match ($driver) {
            'sqlite' => $this->backupSqlite($backupDir, $timestamp),
            'mysql', 'mariadb' => $this->backupMysql($backupDir, $timestamp),
            default => throw new RuntimeException("Database backup is not supported for driver [{$driver}]."),
        };
    }

    public function downloadResponse(array $backup): BinaryFileResponse
    {
        return response()->download(
            $backup['path'],
            $backup['filename'],
            ['Content-Type' => $backup['mime']],
        )->deleteFileAfterSend(false);
    }

    private function backupSqlite(string $backupDir, string $timestamp): array
    {
        $databasePath = DB::connection()->getDatabaseName();

        if (! is_string($databasePath) || ! File::exists($databasePath)) {
            throw new RuntimeException('SQLite database file was not found.');
        }

        $filename = 'pulse-sqlite-'.$timestamp.'.sqlite';
        $destination = $backupDir.DIRECTORY_SEPARATOR.$filename;

        $this->exportSqliteDatabase($databasePath, $destination);

        return [
            'driver' => 'sqlite',
            'path' => $destination,
            'filename' => $filename,
            'mime' => 'application/x-sqlite3',
            'size' => File::size($destination),
        ];
    }

    private function exportSqliteDatabase(string $sourcePath, string $destinationPath): void
    {
        if (File::exists($destinationPath)) {
            File::delete($destinationPath);
        }

        $escapedDestination = str_replace("'", "''", $destinationPath);

        try {
            DB::connection()->getPdo()->exec("VACUUM INTO '{$escapedDestination}'");

            return;
        } catch (\Throwable) {
            // Fall back to a file copy when VACUUM INTO is unavailable.
        }

        File::copy($sourcePath, $destinationPath);

        foreach (['-wal', '-shm'] as $suffix) {
            $sidecar = $sourcePath.$suffix;

            if (File::exists($sidecar)) {
                File::copy($sidecar, $destinationPath.$suffix);
            }
        }
    }

    private function backupMysql(string $backupDir, string $timestamp): array
    {
        $config = DB::connection()->getConfig();
        $filename = 'pulse-mysql-'.$timestamp.'.sql';
        $destination = $backupDir.DIRECTORY_SEPARATOR.$filename;

        $command = [
            'mysqldump',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 3306),
            '--user='.($config['username'] ?? 'root'),
            '--single-transaction',
            '--routines',
            '--triggers',
            $config['database'] ?? '',
        ];

        $environment = [];

        if (! empty($config['password'])) {
            $environment['MYSQL_PWD'] = $config['password'];
        }

        $result = Process::timeout(300)
            ->env($environment)
            ->run($command);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output() ?: 'mysqldump failed.'));
        }

        File::put($destination, $result->output());

        return [
            'driver' => 'mysql',
            'path' => $destination,
            'filename' => $filename,
            'mime' => 'application/sql',
            'size' => File::size($destination),
        ];
    }

    public function pruneOldCopies(): void
    {
        $keep = max(0, (int) config('backup.keep_copies', 10));

        if ($keep === 0) {
            return;
        }

        $backupDir = storage_path('app/backups');

        if (! File::isDirectory($backupDir)) {
            return;
        }

        $files = collect(File::files($backupDir))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        $files->slice($keep)->each(fn ($file) => File::delete($file->getPathname()));
    }

}
