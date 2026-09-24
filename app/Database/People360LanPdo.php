<?php

namespace App\Database;

use App\Services\People360LanClient;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

class People360LanPdo extends PDO
{
    private bool $inTransaction = false;

    private string $lastInsertId = '0';

    public function __construct(
        private readonly string $address,
        private readonly int $port,
    ) {
        parent::__construct('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return People360LanStatement::make($this, $query);
    }

    public function exec(string $statement): int|false
    {
        $result = $this->run($statement, []);

        return (int) ($result['row_count'] ?? 0);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $statement = $this->prepare($query);
        if (! $statement instanceof People360LanStatement) {
            return false;
        }

        if ($fetchMode !== null) {
            $statement->setFetchMode($fetchMode, ...$fetchModeArgs);
        }

        $statement->execute();

        return $statement;
    }

    public function beginTransaction(): bool
    {
        $this->exec('BEGIN');

        return true;
    }

    public function commit(): bool
    {
        $this->exec('COMMIT');

        return true;
    }

    public function rollBack(): bool
    {
        $this->exec('ROLLBACK');

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->lastInsertId;
    }

    public function quote(string $string, int $type = PDO::PARAM_STR): string|false
    {
        return "'".str_replace("'", "''", $string)."'";
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return true;
    }

    /**
     * @param  array<int|string, mixed>  $bindings
     * @return array{rows: list<array<string, mixed>>, row_count: int, last_insert_id: string, in_transaction: bool}
     */
    public function run(string $sql, array $bindings): array
    {
        try {
            $result = app(People360LanClient::class)->runSql($this->address, $this->port, $sql, $bindings);
        } catch (RuntimeException $exception) {
            throw new PDOException($exception->getMessage(), 0, $exception);
        }

        $this->inTransaction = (bool) ($result['in_transaction'] ?? false);
        $this->lastInsertId = (string) ($result['last_insert_id'] ?? '0');

        return $result;
    }
}

class People360LanStatement extends PDOStatement
{
    /** @var array<int|string, mixed> */
    private array $bindings = [];

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    private int $cursor = 0;

    private int $affected = 0;

    private int $fetchMode = PDO::FETCH_OBJ;

    private function __construct(
        private readonly People360LanPdo $connection,
        private readonly string $sql,
    ) {}

    public static function make(People360LanPdo $connection, string $sql): self
    {
        return new self($connection, $sql);
    }

    public function setFetchMode(int $mode, mixed ...$args): true
    {
        $this->fetchMode = $mode;

        return true;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        if (is_resource($value)) {
            $value = stream_get_contents($value) ?: '';
        }

        $this->bindings[$param] = $value;

        return true;
    }

    public function execute(?array $params = null): bool
    {
        $result = $this->connection->run($this->sql, $params ?? $this->bindings);
        $this->rows = $result['rows'];
        $this->affected = (int) $result['row_count'];
        $this->cursor = 0;

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if (! isset($this->rows[$this->cursor])) {
            return false;
        }

        $row = $this->rows[$this->cursor];
        $this->cursor++;

        return $this->cast($row, $mode === PDO::FETCH_DEFAULT ? $this->fetchMode : $mode);
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $mode = $mode === PDO::FETCH_DEFAULT ? $this->fetchMode : $mode;
        $rows = [];

        foreach ($this->rows as $row) {
            $cast = $this->cast($row, $mode);
            if ($mode === PDO::FETCH_COLUMN) {
                $rows[] = $cast;
            } else {
                $rows[] = $cast;
            }
        }

        return $rows;
    }

    public function rowCount(): int
    {
        return $this->affected;
    }

    public function closeCursor(): bool
    {
        $this->rows = [];
        $this->cursor = 0;

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function cast(array $row, int $mode): mixed
    {
        if ($mode === PDO::FETCH_ASSOC) {
            return $row;
        }

        if ($mode === PDO::FETCH_NUM) {
            return array_values($row);
        }

        if ($mode === PDO::FETCH_COLUMN) {
            return array_values($row)[0] ?? null;
        }

        return (object) $row;
    }
}
