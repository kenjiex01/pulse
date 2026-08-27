<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Copy row data from a temporary SQLite file (desktop backup) into the active MySQL connection.
 *
 * Used for browser/dev restore when users upload a People360 desktop .sql dump (PRAGMA / SQLite syntax).
 */
class DesktopSqliteToMysqlImporter
{
    private const CHUNK_SIZE = 250;

    public function import(string $sqliteDatabasePath): void
    {
        if (! is_file($sqliteDatabasePath)) {
            throw new RuntimeException('Temporary SQLite database was not found.');
        }

        $sqlite = new \PDO('sqlite:'.$sqliteDatabasePath, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        $tables = $sqlite->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name",
        )->fetchAll(\PDO::FETCH_COLUMN);

        if ($tables === []) {
            throw new RuntimeException('Desktop backup did not contain any tables.');
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Desktop SQLite import is only supported when the default connection is MySQL.');
        }

        $connection->statement('SET FOREIGN_KEY_CHECKS=0');
        $connection->statement('SET UNIQUE_CHECKS=0');

        try {
            foreach ($tables as $table) {
                $table = (string) $table;

                if (! Schema::hasTable($table)) {
                    continue;
                }

                $connection->table($table)->truncate();
            }

            foreach ($tables as $table) {
                $table = (string) $table;

                if (! Schema::hasTable($table)) {
                    continue;
                }

                $this->copyTable($sqlite, $table, $connection);
            }
        } finally {
            $connection->statement('SET UNIQUE_CHECKS=1');
            $connection->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function copyTable(\PDO $sqlite, string $table, \Illuminate\Database\Connection $connection): void
    {
        $quoted = '"'.str_replace('"', '""', $table).'"';
        $count = (int) $sqlite->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();

        if ($count === 0) {
            return;
        }

        $offset = 0;

        while ($offset < $count) {
            $statement = $sqlite->query(
                "SELECT * FROM {$quoted} LIMIT ".self::CHUNK_SIZE.' OFFSET '.$offset,
            );

            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

            if ($rows === []) {
                break;
            }

            $normalized = array_map(fn (array $row) => $this->normalizeRow($row), $rows);

            $connection->table($table)->insert($normalized);

            $offset += self::CHUNK_SIZE;
        }
    }

    /** @param array<string, mixed> $row */
    private function normalizeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value === '') {
                // Keep empty strings — MySQL columns may be NOT NULL varchar.
                continue;
            }
        }

        return $row;
    }
}
