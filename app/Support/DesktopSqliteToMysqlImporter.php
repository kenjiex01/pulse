<?php

namespace App\Support;

use Illuminate\Database\QueryException;
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
            $allowed = array_flip(Schema::getColumnListing($table));
            $normalized = array_map(
                fn (array $row) => array_intersect_key($row, $allowed),
                $normalized,
            );

            $this->insertChunk($connection, $table, $normalized);

            $offset += self::CHUNK_SIZE;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertChunk(\Illuminate\Database\Connection $connection, string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        try {
            $connection->table($table)->insert($rows);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKey($exception)) {
                throw $exception;
            }

            foreach ($rows as $row) {
                $this->insertOne($connection, $table, $row);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function insertOne(\Illuminate\Database\Connection $connection, string $table, array $row): void
    {
        $attempt = $row;

        for ($try = 0; $try < 8; $try++) {
            try {
                $connection->table($table)->insert($attempt);

                return;
            } catch (QueryException $exception) {
                if (! $this->isDuplicateKey($exception)) {
                    throw $exception;
                }

                $adjusted = $this->adjustDuplicateRow($connection, $table, $attempt, $exception);

                if ($adjusted === null) {
                    return;
                }

                $attempt = $adjusted;
            }
        }

        throw new RuntimeException("Could not import a duplicate row into [{$table}].");
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null Null skips a duplicate primary key.
     */
    private function adjustDuplicateRow(
        \Illuminate\Database\Connection $connection,
        string $table,
        array $row,
        QueryException $exception,
    ): ?array {
        if (! preg_match("/Duplicate entry '(.+)' for key '([^']+)'/", $exception->getMessage(), $matches)) {
            return null;
        }

        $duplicateValue = $matches[1];
        $indexName = $matches[2];
        $indexName = str_contains($indexName, '.') ? substr($indexName, strrpos($indexName, '.') + 1) : $indexName;

        foreach (Schema::getIndexes($table) as $index) {
            $name = (string) ($index['name'] ?? '');

            if ($name !== $indexName) {
                continue;
            }

            if (($index['primary'] ?? false) === true) {
                return null;
            }

            foreach ($index['columns'] ?? [] as $column) {
                if (! array_key_exists($column, $row)) {
                    continue;
                }

                if (strcasecmp((string) $row[$column], $duplicateValue) !== 0) {
                    continue;
                }

                $row[$column] = $this->suffixUniqueValue($connection, $table, (string) $column, (string) $row[$column], $row);

                return $row;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function suffixUniqueValue(
        \Illuminate\Database\Connection $connection,
        string $table,
        string $column,
        string $value,
        array $row,
    ): string {
        unset($connection);

        $suffix = isset($row['id']) && $row['id'] !== '' && $row['id'] !== null
            ? '-'.$row['id']
            : '-dup';
        $max = 255;

        foreach (Schema::getColumns($table) as $definition) {
            if (($definition['name'] ?? '') !== $column) {
                continue;
            }

            if (preg_match('/\((\d+)\)/', (string) ($definition['type'] ?? ''), $length)) {
                $max = (int) $length[1];
            }
        }

        $trimmed = substr($value, 0, max(1, $max - strlen($suffix)));

        return $trimmed.$suffix;
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();

        return $sqlState === '23000' && str_contains($exception->getMessage(), 'Duplicate entry');
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
