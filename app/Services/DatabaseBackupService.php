<?php

namespace App\Services;

use App\Support\DesktopSqliteToMysqlImporter;
use App\Support\MysqlToSqliteDumpConverter;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\ReportSeeder;
use Database\Seeders\RoleModuleSeeder;
use Database\Seeders\SubModuleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupService
{
    public function create(): array
    {
        $driver = DB::connection()->getDriverName();
        // uniqid prevents same-second restores from overwriting the SQL being imported.
        $filename = 'pulse-db-'.now()->format('Y-m-d-H-i-s').'-'.uniqid('', true).'.sql';
        $backupDir = storage_path('app/backups');

        File::ensureDirectoryExists($backupDir);

        return match ($driver) {
            'sqlite' => $this->backupSqlite($backupDir, $filename, excludeModuleCatalog: true),
            'mysql', 'mariadb' => $this->backupMysql($backupDir, $filename, excludeModuleCatalog: true),
            default => throw new RuntimeException("Database backup is not supported for driver [{$driver}]."),
        };
    }

    /**
     * Run after a successful SQL restore: migrate schema, then sync module catalog from seeders.
     */
    public function finalizeRestoredDatabase(): void
    {
        $this->reconcileModuleCatalogSchemaAfterRestore();

        try {
            Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        } catch (\Throwable $exception) {
            Log::warning('Post-restore migrate failed.', ['message' => $exception->getMessage()]);
        }

        $this->refreshModuleCatalogAfterRestore();
        $this->ensureReportCatalogIfMissing();
        $this->resetDesktopBootstrapVersionMarker();
    }

    /**
     * Heal desktop DBs left without module tables after an older SQL restore (500 on dashboard).
     */
    public function repairModuleCatalogIfMissing(): void
    {
        $this->reconcileModuleCatalogSchemaAfterRestore();

        if (! Schema::hasTable('tbl_modules')) {
            return;
        }

        if ((int) DB::table('tbl_modules')->count() < 1) {
            $this->refreshModuleCatalogAfterRestore();
        }

        $this->ensureReportCatalogIfMissing();
    }

    /**
     * @return array{
     *     driver: string,
     *     path: string,
     *     filename: string,
     *     mime: string,
     *     size: int,
     *     gz_path: string,
     *     gz_filename: string,
     *     gz_mime: string,
     *     gz_size: int
     * }
     */
    public function createGzipped(): array
    {
        $backup = $this->create();
        $gzPath = $backup['path'].'.gz';

        $this->gzipFile($backup['path'], $gzPath);

        return array_merge($backup, [
            'gz_path' => $gzPath,
            'gz_filename' => $backup['filename'].'.gz',
            'gz_mime' => 'application/gzip',
            'gz_size' => File::size($gzPath),
        ]);
    }

    public function downloadResponse(array $backup): BinaryFileResponse
    {
        return response()->download(
            $backup['path'],
            $backup['filename'],
            ['Content-Type' => $backup['mime']],
        )->deleteFileAfterSend(false);
    }

    /**
     * @return array{safety_backup: string, bytes: int}
     */
    public function restoreFromSqlFile(string $sqlFilePath): array
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true) && $this->sqlDumpLooksLikeSqlite($sqlFilePath)) {
            return $this->restoreMysqlFromDesktopSqliteDump($sqlFilePath);
        }

        return match ($driver) {
            'sqlite' => $this->restoreSqliteFromFile($sqlFilePath),
            'mysql', 'mariadb' => $this->restoreMysqlFromFile($sqlFilePath),
            default => throw new RuntimeException("SQL file restore is not supported for driver [{$driver}]."),
        };
    }

    /**
     * @return array{safety_backup: string, bytes: int}
     */
    private function restoreSqliteFromFile(string $sqlFilePath): array
    {
        $databasePath = DB::connection()->getDatabaseName();

        if (! is_string($databasePath) || $databasePath === '') {
            throw new RuntimeException('SQLite database path is not configured.');
        }

        if (! File::exists($sqlFilePath) || File::size($sqlFilePath) <= 0) {
            throw new RuntimeException('SQL file is empty or missing.');
        }

        $bytes = File::size($sqlFilePath);
        $importDir = storage_path('app/backups/imports');
        File::ensureDirectoryExists($importDir);

        // Copy first so a same-folder safety backup can never clobber the source dump.
        // A MySQL / phpMyAdmin dump is translated to SQLite-compatible SQL on the way in.
        $stableImportPath = $importDir.DIRECTORY_SEPARATOR.'active-'.uniqid('', true).'.sql';
        $this->prepareSqliteImportFile($sqlFilePath, $stableImportPath);

        // Always take a safety backup we can roll back to before touching the live DB.
        $safetyBackup = $this->createSafetySnapshot();

        try {
            // Validate the dump against a throwaway DB first. A bad or wrong-format file
            // (e.g. MySQL dump) fails here WITHOUT ever wiping the live database.
            $this->assertDumpRestorable($stableImportPath);

            $this->disconnectAllDatabaseConnections();

            try {
                $this->restoreSqliteInPlace($databasePath, $stableImportPath);
            } catch (\Throwable $inPlaceException) {
                Log::warning('SQLite in-place restore failed; attempting file replace fallback.', [
                    'message' => $inPlaceException->getMessage(),
                ]);

                if (PHP_OS_FAMILY === 'Windows') {
                    // Live DB may be mid-wipe — roll back to the safety backup so the
                    // app never lands on an empty database (which 500s every page).
                    $this->recoverFromSafetyBackup($databasePath, $safetyBackup['path']);

                    throw new RuntimeException(
                        'SQL restore failed: '.$inPlaceException->getMessage(),
                        0,
                        $inPlaceException,
                    );
                }

                $this->releaseSqliteDatabaseFiles($databasePath);
                File::ensureDirectoryExists(dirname($databasePath));
                File::put($databasePath, '');
                $this->importSqliteDumpFromFile($databasePath, $stableImportPath);
                $this->assertSqliteHasTables($databasePath);
            }

            config([
                'database.connections.sqlite.database' => $databasePath,
            ]);

            DB::purge('sqlite');
            DB::reconnect('sqlite');

            return [
                'safety_backup' => $safetyBackup['filename'],
                'bytes' => $bytes,
            ];
        } catch (\Throwable $exception) {
            // Guarantee the live DB is usable even if the import wiped it before failing.
            $this->recoverFromSafetyBackup($databasePath, $safetyBackup['path']);

            throw $exception;
        } finally {
            if (File::exists($stableImportPath)) {
                File::delete($stableImportPath);
            }
        }
    }

    /**
     * Write a SQLite-ready copy of the uploaded dump to $destinationPath.
     *
     * SQLite backups are copied verbatim; MySQL / phpMyAdmin dumps are translated so
     * `SET`, backticks, `ENGINE=`, `AUTO_INCREMENT`, and escaped strings import cleanly.
     */
    private function prepareSqliteImportFile(string $sqlFilePath, string $destinationPath): void
    {
        $sql = File::get($sqlFilePath);

        if (MysqlToSqliteDumpConverter::looksLikeMysqlDump($sql)) {
            $sql = (new MysqlToSqliteDumpConverter)->convert($sql);
        }

        $sql = $this->stripModuleCatalogFromSqlDump($sql);

        File::put($destinationPath, $sql);
    }

    /**
     * Import the dump into a disposable SQLite file to prove it is valid before we
     * wipe the live database. Throws with a clear message when the file cannot be restored.
     */
    private function assertDumpRestorable(string $sqlFilePath): void
    {
        $stagingPath = storage_path('app/backups/imports/staging-'.uniqid('', true).'.sqlite');
        File::ensureDirectoryExists(dirname($stagingPath));

        if (File::exists($stagingPath)) {
            File::delete($stagingPath);
        }

        File::put($stagingPath, '');

        try {
            $this->importSqliteDumpWithPdo($stagingPath, $sqlFilePath);

            if (! $this->sqliteHasUserTables($stagingPath)) {
                throw new RuntimeException('The uploaded SQL file did not produce any tables. Make sure it is a People360 SQLite backup (.sql), not a MySQL or partial dump.');
            }
        } finally {
            foreach ([$stagingPath, $stagingPath.'-wal', $stagingPath.'-shm', $stagingPath.'-journal'] as $path) {
                if (File::exists($path)) {
                    @unlink($path);
                }
            }
        }
    }

    /**
     * Re-import a known-good safety backup so the live DB is never left empty after a failed restore.
     */
    private function recoverFromSafetyBackup(string $databasePath, string $safetyBackupPath): void
    {
        try {
            if ($this->sqliteHasUserTables($databasePath)) {
                return;
            }

            if (! File::exists($safetyBackupPath) || File::size($safetyBackupPath) <= 0) {
                return;
            }

            $this->disconnectAllDatabaseConnections();
            // Wipe leftover partial objects, then re-import the known-good safety dump.
            $this->restoreSqliteInPlace($databasePath, $safetyBackupPath);

            config(['database.connections.sqlite.database' => $databasePath]);
            DB::purge('sqlite');
            DB::reconnect('sqlite');
        } catch (\Throwable $recoveryException) {
            Log::error('Failed to roll back to safety backup after failed SQL restore.', [
                'message' => $recoveryException->getMessage(),
            ]);

            report($recoveryException);
        }
    }

    /**
     * @return array{safety_backup: string, bytes: int}
     */
    private function restoreMysqlFromDesktopSqliteDump(string $sqlFilePath): array
    {
        $this->assertDumpRestorable($sqlFilePath);

        $bytes = File::size($sqlFilePath);
        $safetyBackup = $this->createSafetySnapshot();

        $importDir = storage_path('app/backups/imports');
        File::ensureDirectoryExists($importDir);

        $preparedPath = $importDir.DIRECTORY_SEPARATOR.'mysql-bridge-prepared-'.uniqid('', true).'.sql';
        $tempSqlite = $importDir.DIRECTORY_SEPARATOR.'mysql-bridge-'.uniqid('', true).'.sqlite';

        File::put($tempSqlite, '');

        try {
            $this->prepareSqliteImportFile($sqlFilePath, $preparedPath);
            $this->importSqliteDumpFromFile($tempSqlite, $preparedPath);
            $this->assertSqliteHasTables($tempSqlite);

            (new DesktopSqliteToMysqlImporter)->import($tempSqlite);

            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($this->moduleCatalogTablesExcludedFromDump() as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            DB::purge((string) config('database.default'));
            DB::reconnect();

            return [
                'safety_backup' => $safetyBackup['filename'],
                'bytes' => $bytes,
            ];
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Desktop backup import to MySQL failed: '.$exception->getMessage(),
                0,
                $exception,
            );
        } finally {
            foreach ([$preparedPath, $tempSqlite, $tempSqlite.'-wal', $tempSqlite.'-shm', $tempSqlite.'-journal'] as $path) {
                if (File::exists($path)) {
                    @unlink($path);
                }
            }
        }
    }

    private function restoreMysqlFromFile(string $sqlFilePath): array
    {
        $sql = File::get($sqlFilePath);

        if (! filled(trim($sql))) {
            throw new RuntimeException('SQL file is empty.');
        }

        $sql = $this->stripModuleCatalogFromSqlDump($sql);

        $safetyBackup = $this->createSafetySnapshot();
        $connectionName = (string) config('database.default', 'mysql');
        $config = DB::connection($connectionName)->getConfig();

        DB::disconnect($connectionName);

        $command = [
            'mysql',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 3306),
            '--user='.($config['username'] ?? 'root'),
            $config['database'] ?? '',
        ];

        $environment = [];

        if (! empty($config['password'])) {
            $environment['MYSQL_PWD'] = $config['password'];
        }

        $result = Process::forever()
            ->env($environment)
            ->input($sql)
            ->run($command);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output() ?: 'mysql import failed.'));
        }

        DB::purge($connectionName);
        DB::reconnect($connectionName);

        return [
            'safety_backup' => $safetyBackup['filename'],
            'bytes' => strlen($sql),
        ];
    }

    private function backupSqlite(string $backupDir, string $filename, bool $excludeModuleCatalog = true): array
    {
        $databasePath = DB::connection()->getDatabaseName();

        if (! is_string($databasePath) || ! File::exists($databasePath)) {
            throw new RuntimeException('SQLite database file was not found.');
        }

        $destination = $backupDir.DIRECTORY_SEPARATOR.$filename;

        $this->exportSqliteAsSql($databasePath, $destination, $excludeModuleCatalog);

        return [
            'driver' => 'sqlite',
            'path' => $destination,
            'filename' => $filename,
            'mime' => 'application/sql',
            'size' => File::size($destination),
        ];
    }

    /**
     * Full database snapshot before restore (includes module catalog for rollback).
     *
     * @return array{driver: string, path: string, filename: string, mime: string, size: int}
     */
    private function createSafetySnapshot(): array
    {
        $driver = DB::connection()->getDriverName();
        $filename = 'pulse-db-safety-'.now()->format('Y-m-d-H-i-s').'-'.uniqid('', true).'.sql';
        $backupDir = storage_path('app/backups');

        File::ensureDirectoryExists($backupDir);

        return match ($driver) {
            'sqlite' => $this->backupSqlite($backupDir, $filename, excludeModuleCatalog: false),
            'mysql', 'mariadb' => $this->backupMysql($backupDir, $filename, excludeModuleCatalog: false),
            default => throw new RuntimeException("Database backup is not supported for driver [{$driver}]."),
        };
    }

    private function exportSqliteAsSql(string $sourcePath, string $destinationPath, bool $excludeModuleCatalog = true): void
    {
        if (File::exists($destinationPath)) {
            File::delete($destinationPath);
        }

        $sqlite3 = $this->resolveSqlite3Binary();

        if ($sqlite3 !== null) {
            $result = Process::timeout(300)->run([$sqlite3, $sourcePath, '.dump']);

            if ($result->successful() && filled(trim($result->output()))) {
                $sql = $result->output();

                if ($excludeModuleCatalog) {
                    $sql = $this->stripModuleCatalogFromSqlDump($sql);
                }

                File::put($destinationPath, $sql);

                return;
            }
        }

        File::put(
            $destinationPath,
            $this->buildSqliteSqlDump($sourcePath, $excludeModuleCatalog),
        );
    }

    private function resolveSqlite3Binary(): ?string
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $where = Process::run(['where', 'sqlite3']);

                if ($where->successful()) {
                    $path = trim(strtok(trim($where->output()), "\r\n") ?: '');

                    return $path !== '' ? $path : null;
                }

                return null;
            }

            $which = Process::run(['which', 'sqlite3']);

            if ($which->successful() && filled(trim($which->output()))) {
                return trim($which->output());
            }

            $command = Process::run(['sh', '-c', 'command -v sqlite3']);

            if ($command->successful() && filled(trim($command->output()))) {
                return trim($command->output());
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Close Laravel connections and remove SQLite main/WAL/SHM files (Windows-safe retries).
     */
    private function releaseSqliteDatabaseFiles(string $databasePath): void
    {
        $this->disconnectAllDatabaseConnections();

        try {
            $pdo = new \PDO('sqlite:'.$databasePath, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            unset($pdo);
        } catch (\Throwable) {
            // Connection may already be unusable; continue with file removal.
        }

        foreach ([$databasePath, $databasePath.'-wal', $databasePath.'-shm', $databasePath.'-journal'] as $path) {
            $this->deleteFileWithRetry($path);
        }
    }

    /**
     * Replace schema/data without deleting pulse.sqlite — avoids Windows file-lock on the main DB file.
     */
    private function restoreSqliteInPlace(string $databasePath, string $sqlFilePath): void
    {
        if (! File::exists($databasePath)) {
            File::ensureDirectoryExists(dirname($databasePath));
            File::put($databasePath, '');
        }

        $pdo = new \PDO('sqlite:'.$databasePath, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        try {
            $this->wipeSqliteUserObjects($pdo);
        } finally {
            unset($pdo);
            gc_collect_cycles();
        }

        $this->importSqliteDumpFromFile($databasePath, $sqlFilePath);
        $this->assertSqliteHasTables($databasePath);
    }

    private function wipeSqliteUserObjects(\PDO $pdo): void
    {
        $pdo->exec('PRAGMA foreign_keys=OFF');

        try {
            $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (\Throwable) {
            // Continue — checkpoint may fail on empty or legacy journal modes.
        }

        $views = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'view' AND name NOT LIKE 'sqlite_%'",
        )->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($views as $viewName) {
            $pdo->exec('DROP VIEW IF EXISTS "'.$this->escapeSqliteIdentifier((string) $viewName).'"');
        }

        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'",
        )->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $tableName) {
            $pdo->exec('DROP TABLE IF EXISTS "'.$this->escapeSqliteIdentifier((string) $tableName).'"');
        }

        try {
            $pdo->exec('PRAGMA journal_mode=DELETE');
        } catch (\Throwable) {
            // Best effort — reduces Windows WAL lock issues before import.
        }

        $indexes = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'index' AND name NOT LIKE 'sqlite_%'",
        )->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($indexes as $indexName) {
            $pdo->exec('DROP INDEX IF EXISTS "'.$this->escapeSqliteIdentifier((string) $indexName).'"');
        }
    }

    private function escapeSqliteIdentifier(string $identifier): string
    {
        return str_replace('"', '""', $identifier);
    }

    private function disconnectAllDatabaseConnections(): void
    {
        foreach (array_keys(config('database.connections', [])) as $connectionName) {
            if (! is_string($connectionName) || $connectionName === '') {
                continue;
            }

            try {
                DB::connection($connectionName)->disconnect();
            } catch (\Throwable) {
                // Ignore stale or unused connection names.
            }
        }

        DB::purge();
        gc_collect_cycles();
    }

    private function deleteFileWithRetry(string $path, int $attempts = 8): void
    {
        if (! File::exists($path)) {
            return;
        }

        for ($i = 0; $i < $attempts; $i++) {
            try {
                if (@unlink($path) || ! File::exists($path)) {
                    return;
                }
            } catch (\Throwable) {
                // Retry — Windows often holds SQLite locks briefly after disconnect.
            }

            usleep(PHP_OS_FAMILY === 'Windows' ? 250_000 : 150_000);
            gc_collect_cycles();
        }

        if (File::exists($path)) {
            throw new RuntimeException('Unable to replace SQLite database file (still locked): '.$path);
        }
    }

    private function buildSqliteSqlDump(string $databasePath, bool $excludeModuleCatalog = true): string
    {
        $pdo = new \PDO('sqlite:'.$databasePath, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        $excluded = $excludeModuleCatalog ? $this->moduleCatalogTablesExcludedFromDump() : [];

        $lines = [
            'PRAGMA foreign_keys=OFF;',
            'BEGIN TRANSACTION;',
        ];

        $tables = $pdo->query(
            "SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name",
        )->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($tables as $table) {
            $tableName = (string) $table['name'];

            if (in_array($tableName, $excluded, true)) {
                continue;
            }

            if (! empty($table['sql'])) {
                $lines[] = $table['sql'].';';
            }

            $columns = $pdo->query('PRAGMA table_info("'.str_replace('"', '""', $tableName).'")')
                ->fetchAll(\PDO::FETCH_ASSOC);

            if ($columns === []) {
                continue;
            }

            $columnNames = array_map(
                fn (array $column) => '"'.str_replace('"', '""', $column['name']).'"',
                $columns,
            );

            $rows = $pdo->query('SELECT * FROM "'.str_replace('"', '""', $tableName).'"');

            while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
                $values = array_map(
                    fn (string $column) => $this->quoteSqliteValue($row[$column] ?? null),
                    array_column($columns, 'name'),
                );

                $lines[] = 'INSERT INTO "'.$tableName.'" ('.implode(', ', $columnNames).') VALUES ('.implode(', ', $values).');';
            }
        }

        $indexes = $pdo->query(
            "SELECT sql FROM sqlite_master WHERE type = 'index' AND sql IS NOT NULL ORDER BY name",
        )->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($indexes as $indexSql) {
            if ($excludeModuleCatalog && $this->sqlStatementReferencesExcludedCatalogTable((string) $indexSql)) {
                continue;
            }

            $lines[] = $indexSql.';';
        }

        $lines[] = 'COMMIT;';

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function quoteSqliteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (! is_string($value)) {
            $value = (string) $value;
        }

        return "'".str_replace("'", "''", $value)."'";
    }

    private function backupMysql(string $backupDir, string $filename, bool $excludeModuleCatalog = true): array
    {
        $config = DB::connection()->getConfig();
        $destination = $backupDir.DIRECTORY_SEPARATOR.$filename;

        $command = [
            'mysqldump',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 3306),
            '--user='.($config['username'] ?? 'root'),
            '--single-transaction',
            '--routines',
            '--triggers',
        ];

        if ($excludeModuleCatalog) {
            $database = (string) ($config['database'] ?? '');

            foreach ($this->moduleCatalogTablesExcludedFromDump() as $table) {
                if ($database !== '') {
                    $command[] = '--ignore-table='.$database.'.'.$table;
                }
            }
        }

        $command[] = $config['database'] ?? '';

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

    private function gzipFile(string $sourcePath, string $destinationPath): void
    {
        if (File::exists($destinationPath)) {
            File::delete($destinationPath);
        }

        $input = fopen($sourcePath, 'rb');

        if ($input === false) {
            throw new RuntimeException('Unable to read backup file for gzip compression.');
        }

        $output = gzopen($destinationPath, 'wb9');

        if ($output === false) {
            fclose($input);

            throw new RuntimeException('Unable to create gzip backup file.');
        }

        try {
            while (! feof($input)) {
                $chunk = fread($input, 1024 * 512);

                if ($chunk === false) {
                    throw new RuntimeException('Failed while reading backup file for gzip compression.');
                }

                if ($chunk !== '' && gzwrite($output, $chunk) === false) {
                    throw new RuntimeException('Failed while writing gzip backup file.');
                }
            }
        } finally {
            fclose($input);
            gzclose($output);
        }
    }

    private function importSqliteDumpFromFile(string $databasePath, string $sqlFilePath): void
    {
        // Windows desktop: avoid a second sqlite3 process locking the same file People360 already uses.
        if (PHP_OS_FAMILY === 'Windows') {
            $this->importSqliteDumpWithPdo($databasePath, $sqlFilePath);
            $this->assertSqliteHasTables($databasePath);

            return;
        }

        $sqlite3 = $this->resolveSqlite3Binary();

        if ($sqlite3 !== null) {
            try {
                // Prefer .read over stdin so large dumps are not loaded into PHP memory.
                $readPath = str_replace('\\', '/', $sqlFilePath);
                $result = Process::forever()->run([
                    $sqlite3,
                    $databasePath,
                    ".read '".str_replace("'", "''", $readPath)."'",
                ]);

                if ($result->successful() && $this->sqliteHasUserTables($databasePath)) {
                    return;
                }
            } catch (\Throwable) {
                // Fall through to PDO import.
            }
        }

        $this->importSqliteDumpWithPdo($databasePath, $sqlFilePath);
    }

    private function importSqliteDumpWithPdo(string $databasePath, string $sqlFilePath): void
    {
        $sql = File::get($sqlFilePath);

        if (! filled(trim($sql))) {
            throw new RuntimeException('SQL file is empty.');
        }

        $pdo = new \PDO('sqlite:'.$databasePath, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        try {
            $pdo->exec('PRAGMA foreign_keys=OFF');

            $executed = 0;

            foreach ($this->splitSqlStatements($sql) as $statement) {
                if ($statement === '') {
                    continue;
                }

                $pdo->exec($statement);
                $executed++;
            }

            if ($executed === 0) {
                throw new RuntimeException('SQL file contained no executable statements.');
            }
        } catch (\Throwable $exception) {
            throw new RuntimeException('SQLite PDO import failed: '.$exception->getMessage(), 0, $exception);
        }
    }

    private function assertSqliteHasTables(string $databasePath): void
    {
        if (! $this->sqliteHasUserTables($databasePath)) {
            throw new RuntimeException('SQL restore finished but no tables were created. The file may be invalid or incomplete.');
        }
    }

    private function sqliteHasUserTables(string $databasePath): bool
    {
        if (! File::exists($databasePath) || File::size($databasePath) <= 0) {
            return false;
        }

        try {
            $pdo = new \PDO('sqlite:'.$databasePath, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            $count = (int) $pdo->query(
                "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'",
            )->fetchColumn();

            return $count > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @deprecated Prefer importSqliteDumpFromFile() to avoid loading large dumps into memory twice.
     */
    private function importSqliteDump(string $databasePath, string $sql): void
    {
        $temp = storage_path('app/backups/imports/tmp-'.uniqid('', true).'.sql');
        File::ensureDirectoryExists(dirname($temp));
        File::put($temp, $sql);

        try {
            $this->importSqliteDumpFromFile($databasePath, $temp);
        } finally {
            if (File::exists($temp)) {
                File::delete($temp);
            }
        }
    }

    /**
     * Split SQL into statements, respecting quoted strings/identifiers and comments so a
     * ';' or newline inside a value never splits a statement mid-way.
     *
     * @return array<int, string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $i = 0;

        while ($i < $length) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            // Line comments: -- ... or # ... (skip to end of line).
            if (($char === '-' && $next === '-') || $char === '#') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }

                continue;
            }

            // Block comments /* ... */.
            if ($char === '/' && $next === '*') {
                $i += 2;

                while ($i < $length && ! ($sql[$i] === '*' && ($i + 1 < $length ? $sql[$i + 1] : '') === '/')) {
                    $i++;
                }

                $i += 2;

                continue;
            }

            // Quoted strings ('...') and identifiers ("...") — copied verbatim (doubling escapes).
            if ($char === "'" || $char === '"') {
                $buffer .= $char;
                $i++;

                while ($i < $length) {
                    $c = $sql[$i];
                    $buffer .= $c;

                    if ($c === $char) {
                        if (($i + 1 < $length ? $sql[$i + 1] : '') === $char) {
                            $buffer .= $sql[$i + 1];
                            $i += 2;

                            continue;
                        }

                        $i++;
                        break;
                    }

                    $i++;
                }

                continue;
            }

            if ($char === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }

                $buffer = '';
                $i++;

                continue;
            }

            $buffer .= $char;
            $i++;
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    private function moduleCatalogTablesExcludedFromDump(): array
    {
        $tables = config('backup.exclude_tables_from_dump', []);

        return is_array($tables) ? array_values(array_filter($tables, 'is_string')) : [];
    }

    private function stripModuleCatalogFromSqlDump(string $sql): string
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $filtered = [];

        foreach ($lines as $line) {
            if ($this->sqlDumpLineReferencesExcludedCatalogTable($line)) {
                continue;
            }

            $filtered[] = $line;
        }

        return implode(PHP_EOL, $filtered);
    }

    private function sqlDumpLineReferencesExcludedCatalogTable(string $line): bool
    {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            return false;
        }

        foreach ($this->moduleCatalogTablesExcludedFromDump() as $table) {
            if ($this->sqlStatementReferencesExcludedCatalogTable($trimmed, $table)) {
                return true;
            }
        }

        return false;
    }

    private function sqlStatementReferencesExcludedCatalogTable(string $sql, ?string $onlyTable = null): bool
    {
        $tables = $onlyTable !== null
            ? [$onlyTable]
            : $this->moduleCatalogTablesExcludedFromDump();

        foreach ($tables as $table) {
            $quoted = preg_quote($table, '/');

            if (preg_match('/^CREATE TABLE(?: IF NOT EXISTS)? (?:[`"\[]?'.$quoted.'[`"\]]?)/i', $sql)) {
                return true;
            }

            if (preg_match('/^INSERT INTO (?:[`"\[]?'.$quoted.'[`"\]]?)/i', $sql)) {
                return true;
            }

            if (preg_match('/^CREATE (?:UNIQUE )?INDEX (?:[`"\[]?[^`"\[]+[`"\]]? )?ON (?:[`"\[]?'.$quoted.'[`"\]]?)/i', $sql)) {
                return true;
            }

            if (preg_match('/^DROP TABLE(?: IF EXISTS)? (?:[`"\[]?'.$quoted.'[`"\]]?)/i', $sql)) {
                return true;
            }
        }

        return false;
    }

    private function refreshModuleCatalogAfterRestore(): void
    {
        foreach ([ModuleSeeder::class, SubModuleSeeder::class, RoleModuleSeeder::class] as $seederClass) {
            try {
                Artisan::call('db:seed', [
                    '--class' => $seederClass,
                    '--force' => true,
                    '--no-interaction' => true,
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Post-restore module catalog seed failed.', [
                    'seeder' => $seederClass,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * SQL dumps omit module catalog tables but keep the old `migrations` rows, so a plain
     * `migrate` skips recreating tbl_modules / sys_sub_modules and the app 500s on login.
     */
    private function reconcileModuleCatalogSchemaAfterRestore(): void
    {
        $catalogTables = $this->moduleCatalogTablesExcludedFromDump();

        if ($catalogTables === []) {
            return;
        }

        $missingTables = array_values(array_filter(
            $catalogTables,
            fn (string $table) => ! Schema::hasTable($table),
        ));

        if ($missingTables === []) {
            return;
        }

        if (! Schema::hasTable('migrations')) {
            return;
        }

        Log::info('Post-restore module catalog tables missing; re-running module migrations.', [
            'missing' => $missingTables,
        ]);

        $moduleMigrationNames = [
            '2026_06_08_120000_create_tbl_modules_table',
            '2026_06_08_130000_create_tbl_role_modules_table',
            '2026_06_09_140000_create_sys_sub_modules_table',
            '2026_06_09_140001_make_tbl_modules_route_name_nullable',
            '2026_06_09_150000_create_tbl_role_sub_modules_table',
        ];

        DB::table('migrations')->whereIn('migration', $moduleMigrationNames)->delete();

        try {
            Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        } catch (\Throwable $exception) {
            Log::warning('Post-restore module catalog migration failed.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * SQL backups include report table schema but usually no catalog rows — Payroll Reports 404s without this.
     */
    public function ensureReportCatalogIfMissing(): void
    {
        if (! Schema::hasTable('lu_report_classifications')) {
            return;
        }

        $classificationCount = (int) DB::table('lu_report_classifications')->count();
        $reportCount = Schema::hasTable('tbl_reports')
            ? (int) DB::table('tbl_reports')->count()
            : 0;

        if ($classificationCount > 0 && $reportCount > 0) {
            return;
        }

        try {
            Artisan::call('db:seed', [
                '--class' => ReportSeeder::class,
                '--force' => true,
                '--no-interaction' => true,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Post-restore report catalog seed failed.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function resetDesktopBootstrapVersionMarker(): void
    {
        $markers = [
            storage_path('app/.desktop-bootstrap-version'),
            storage_path('app/.desktop-schema-state'),
            storage_path('app/.desktop-govt-tables-version'),
        ];

        foreach ($markers as $markerPath) {
            if (File::exists($markerPath)) {
                File::delete($markerPath);
            }
        }
    }

    private function sqlDumpLooksLikeSqlite(string $sqlFilePath): bool
    {
        if (! File::exists($sqlFilePath)) {
            return false;
        }

        $head = ltrim((string) File::get($sqlFilePath, false, null, 0, 8192));

        if ($head === '') {
            return false;
        }

        return str_starts_with($head, 'PRAGMA ')
            || str_contains($head, 'sqlite_master');
    }

}
