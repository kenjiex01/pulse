<?php

namespace App\Support;

/**
 * Best-effort translator from a MySQL / phpMyAdmin `.sql` dump to SQLite-compatible SQL.
 *
 * Desktop Pulse runs on SQLite, but users often restore backups produced by mysqldump
 * (which start with `SET ...`, use backticks, `ENGINE=InnoDB`, `AUTO_INCREMENT`, etc.).
 * This converter strips MySQL-only directives, rewrites identifiers/strings, and reshapes
 * `CREATE TABLE` / `INSERT` statements so the dump imports into SQLite.
 */
class MysqlToSqliteDumpConverter
{
    /**
     * Heuristic: does this dump look like MySQL output rather than a SQLite dump?
     */
    public static function looksLikeMysqlDump(string $sql): bool
    {
        $needles = [
            'ENGINE=',
            'AUTO_INCREMENT',
            'LOCK TABLES',
            'UNLOCK TABLES',
            'DEFAULT CHARSET',
            '/*!',
            'SET SQL_MODE',
            'SET @',
            'CHARACTER SET',
        ];

        foreach ($needles as $needle) {
            if (stripos($sql, $needle) !== false) {
                return true;
            }
        }

        // Backtick-quoted identifiers are MySQL-specific in practice.
        return str_contains($sql, '`');
    }

    public function convert(string $sql): string
    {
        $out = ['PRAGMA foreign_keys=OFF;'];

        foreach ($this->splitStatements($sql) as $statement) {
            $converted = $this->convertStatement($statement);

            if ($converted !== null && trim($converted) !== '') {
                $out[] = rtrim($converted, "; \t\n\r").';';
            }
        }

        return implode("\n", $out)."\n";
    }

    /**
     * Split into statements, dropping comments and respecting quotes/backticks.
     *
     * @return array<int, string>
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $i = 0;

        while ($i < $length) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            // Line comments: -- ... or # ...
            if (($char === '-' && $next === '-') || $char === '#') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }

                continue;
            }

            // Block comments (including /*! executable comments) — dropped.
            if ($char === '/' && $next === '*') {
                $i += 2;

                while ($i < $length && ! ($sql[$i] === '*' && ($i + 1 < $length ? $sql[$i + 1] : '') === '/')) {
                    $i++;
                }

                $i += 2;

                continue;
            }

            // Quoted strings / identifiers — copied verbatim into the buffer.
            if ($char === "'" || $char === '"' || $char === '`') {
                $buffer .= $char;
                $i++;

                while ($i < $length) {
                    $c = $sql[$i];
                    $buffer .= $c;

                    if ($c === '\\' && $char !== '`') {
                        // Backslash escape (not applicable to backtick identifiers).
                        if ($i + 1 < $length) {
                            $buffer .= $sql[$i + 1];
                            $i += 2;

                            continue;
                        }
                    }

                    if ($c === $char) {
                        // Doubled quote = escaped quote, keep scanning.
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

    private function convertStatement(string $statement): ?string
    {
        $keyword = strtoupper((string) preg_replace('/^\s*(\w+).*/s', '$1', $statement));

        return match ($keyword) {
            'CREATE' => $this->convertCreate($statement),
            'INSERT', 'REPLACE' => $this->rewriteIdentifiersAndStrings($statement),
            'DROP' => $this->convertDrop($statement),
            // MySQL session/administrative directives with no SQLite equivalent.
            'SET', 'LOCK', 'UNLOCK', 'USE', 'START', 'COMMIT', 'BEGIN',
            'FLUSH', 'ALTER', 'DELIMITER', 'GRANT', 'REVOKE' => null,
            default => null,
        };
    }

    private function convertDrop(string $statement): ?string
    {
        if (! preg_match('/^\s*DROP\s+TABLE/i', $statement)) {
            return null;
        }

        $statement = $this->rewriteIdentifiersAndStrings($statement);

        if (! preg_match('/IF\s+EXISTS/i', $statement)) {
            $statement = preg_replace('/^\s*DROP\s+TABLE/i', 'DROP TABLE IF EXISTS', $statement);
        }

        return $statement;
    }

    private function convertCreate(string $statement): ?string
    {
        if (! preg_match('/^\s*CREATE\s+TABLE/i', $statement)) {
            // CREATE DATABASE / VIEW / TRIGGER / PROCEDURE not supported on SQLite desktop.
            return null;
        }

        $open = strpos($statement, '(');

        if ($open === false) {
            return null;
        }

        $header = substr($statement, 0, $open);
        // Balanced extraction so parens inside enum/set lists or defaults don't truncate the body.
        [$body] = $this->extractParenGroup($statement, $open);

        if (! preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?([A-Za-z0-9_]+)[`"]?/i', $header, $nameMatch)) {
            return null;
        }

        $tableName = $nameMatch[1];

        $columns = [];
        $primaryKey = [];
        $uniqueIndexes = [];
        $plainIndexes = [];
        $autoIncrementColumn = null;

        foreach ($this->splitTopLevel($body) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (preg_match('/^PRIMARY\s+KEY\s*(\(.*)$/is', $part, $m)) {
                [$inner] = $this->extractParenGroup($m[1], 0);
                $primaryKey = $this->parseColumnList($inner);

                continue;
            }

            if (preg_match('/^(?:CONSTRAINT\s+.+?\s+)?FOREIGN\s+KEY/i', $part)) {
                // Drop FK constraints — restored data may not be inserted in FK order.
                continue;
            }

            if (preg_match('/^UNIQUE\s+(?:KEY|INDEX)?\s*[`"]?\w*[`"]?\s*(\(.*)$/is', $part, $m)) {
                [$inner] = $this->extractParenGroup($m[1], 0);
                $uniqueIndexes[] = ['columns' => $this->parseColumnList($inner)];

                continue;
            }

            if (preg_match('/^(?:KEY|INDEX)\s+[`"]?(\w+)[`"]?\s*(\(.*)$/is', $part, $m)) {
                [$inner] = $this->extractParenGroup($m[2], 0);
                $plainIndexes[] = ['name' => $m[1], 'columns' => $this->parseColumnList($inner)];

                continue;
            }

            if (preg_match('/^(?:FULLTEXT|SPATIAL|CHECK)/i', $part)) {
                continue;
            }

            // Column definition.
            $column = $this->convertColumn($part);

            if ($column === null) {
                continue;
            }

            $columns[] = $column;

            if ($column['auto_increment']) {
                $autoIncrementColumn = $column['name'];
            }
        }

        // Fold a single-column auto-increment PK into INTEGER PRIMARY KEY AUTOINCREMENT.
        $foldAutoIncrement = $autoIncrementColumn !== null
            && count($primaryKey) === 1
            && strcasecmp($primaryKey[0], $autoIncrementColumn) === 0;

        $lines = [];

        foreach ($columns as $column) {
            if ($foldAutoIncrement && strcasecmp($column['name'], (string) $autoIncrementColumn) === 0) {
                $lines[] = '"'.$column['name'].'" INTEGER PRIMARY KEY AUTOINCREMENT';

                continue;
            }

            $lines[] = '"'.$column['name'].'" '.trim($column['type'].' '.$column['attributes']);
        }

        if (! $foldAutoIncrement && $primaryKey !== []) {
            $pkCols = implode(', ', array_map(fn ($c) => '"'.$c.'"', $primaryKey));
            $lines[] = 'PRIMARY KEY ('.$pkCols.')';
        }

        foreach ($uniqueIndexes as $unique) {
            // Represent as table-level UNIQUE constraint (kept inside CREATE TABLE).
            $cols = implode(', ', array_map(fn ($c) => '"'.$c.'"', $unique['columns']));
            $lines[] = 'UNIQUE ('.$cols.')';
        }

        $create = 'CREATE TABLE "'.$tableName.'" ('."\n  ".implode(",\n  ", $lines)."\n);";

        // Non-unique indexes become standalone statements (table-prefixed to avoid name clashes).
        foreach ($plainIndexes as $index) {
            $cols = implode(', ', array_map(fn ($c) => '"'.$c.'"', $index['columns']));
            $indexName = 'idx_'.$tableName.'_'.$index['name'];
            $create .= "\nCREATE INDEX \"".$indexName.'" ON "'.$tableName.'" ('.$cols.');';
        }

        return $create;
    }

    /**
     * @return array{name: string, type: string, attributes: string, auto_increment: bool}|null
     */
    private function convertColumn(string $definition): ?array
    {
        if (! preg_match('/^\s*[`"]?(\w+)[`"]?\s+(.*)$/s', $definition, $m)) {
            return null;
        }

        $name = $m[1];
        $rest = trim($m[2]);

        $autoIncrement = (bool) preg_match('/\bAUTO_INCREMENT\b/i', $rest);

        // Remove MySQL-only clauses first so a value like utf8mb4 can never leak into the type.
        $rest = $this->stripMysqlColumnNoise($rest);

        // Type = first word, then skip an optional balanced (...) size / value list.
        if (! preg_match('/^(\w+)/', $rest, $tm)) {
            return null;
        }

        $typeWord = $tm[1];
        $pos = strlen($typeWord);

        if (isset($rest[$pos]) && $rest[$pos] === '(') {
            [, $pos] = $this->extractParenGroup($rest, $pos);
        }

        $attributes = $this->normalizeColumnAttributes(trim(substr($rest, $pos)));

        return [
            'name' => $name,
            'type' => $this->normalizeType($typeWord),
            'attributes' => $attributes,
            'auto_increment' => $autoIncrement,
        ];
    }

    /**
     * Strip MySQL-only column clauses (charset/collation/comment/auto-increment/etc.).
     */
    private function stripMysqlColumnNoise(string $definition): string
    {
        $definition = preg_replace('/CHARACTER\s+SET\s+\S+/i', ' ', $definition);
        $definition = preg_replace('/CHARSET\s*=\s*\S+/i', ' ', $definition);
        $definition = preg_replace('/COLLATE\s*=?\s*\S+/i', ' ', $definition);
        $definition = preg_replace("/COMMENT\\s+'(?:[^'\\\\]|\\\\.|'')*'/i", ' ', $definition);
        $definition = preg_replace('/ON\s+UPDATE\s+CURRENT_TIMESTAMP(\s*\(\s*\))?/i', ' ', $definition);
        $definition = preg_replace('/\bAUTO_INCREMENT\b/i', ' ', $definition);
        $definition = preg_replace('/\b(?:unsigned|zerofill)\b/i', ' ', $definition);

        return trim((string) preg_replace('/\s+/', ' ', (string) $definition));
    }

    private function normalizeType(string $baseType): string
    {
        $type = strtolower(trim($baseType));

        return match (true) {
            in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'bit', 'bool', 'boolean', 'serial'], true) => 'INTEGER',
            in_array($type, ['decimal', 'numeric', 'dec', 'fixed'], true) => 'NUMERIC',
            in_array($type, ['float', 'double', 'real'], true) => 'REAL',
            in_array($type, ['blob', 'tinyblob', 'mediumblob', 'longblob', 'binary', 'varbinary'], true) => 'BLOB',
            default => 'TEXT',
        };
    }

    private function normalizeColumnAttributes(string $attributes): string
    {
        // Charset introducers on defaults: DEFAULT _utf8mb4'x' → DEFAULT 'x'.
        $attributes = preg_replace('/_(?:utf8mb4|utf8|utf16|latin1|ascii|binary)(?=\s*[\'"])/i', '', $attributes);

        // MySQL 8 emits CURRENT_TIMESTAMP(); SQLite wants it without parentheses.
        $attributes = preg_replace('/CURRENT_TIMESTAMP\s*\(\s*\)/i', 'CURRENT_TIMESTAMP', $attributes);

        // Bit literals b'0' / b'1' → integer literals.
        $attributes = preg_replace_callback("/b'([01]+)'/i", fn ($m) => (string) bindec($m[1]), $attributes);

        return trim((string) preg_replace('/\s+/', ' ', (string) $attributes));
    }

    /**
     * Return the inner content of a balanced parenthesis group and the index just past its close.
     * Respects quoted strings/identifiers so parens inside literals are not miscounted.
     *
     * @return array{0: string, 1: int}
     */
    private function extractParenGroup(string $s, int $openPos): array
    {
        $length = strlen($s);
        $i = $openPos;
        $depth = 0;
        $inner = '';

        while ($i < $length) {
            $char = $s[$i];

            if ($char === "'" || $char === '"' || $char === '`') {
                $token = $char;
                $i++;

                while ($i < $length) {
                    $c = $s[$i];
                    $token .= $c;

                    if ($c === '\\' && $char !== '`' && $i + 1 < $length) {
                        $token .= $s[$i + 1];
                        $i += 2;

                        continue;
                    }

                    if ($c === $char) {
                        if (($i + 1 < $length ? $s[$i + 1] : '') === $char) {
                            $token .= $s[$i + 1];
                            $i += 2;

                            continue;
                        }

                        $i++;
                        break;
                    }

                    $i++;
                }

                if ($depth >= 1) {
                    $inner .= $token;
                }

                continue;
            }

            if ($char === '(') {
                $depth++;

                if ($depth > 1) {
                    $inner .= $char;
                }

                $i++;

                continue;
            }

            if ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    $i++;
                    break;
                }

                $inner .= $char;
                $i++;

                continue;
            }

            if ($depth >= 1) {
                $inner .= $char;
            }

            $i++;
        }

        return [$inner, $i];
    }

    /**
     * Convert backtick identifiers to double-quoted identifiers and MySQL string escapes
     * to SQLite string literals, leaving statement structure intact.
     */
    private function rewriteIdentifiersAndStrings(string $statement): string
    {
        $out = '';
        $length = strlen($statement);
        $i = 0;

        while ($i < $length) {
            $char = $statement[$i];

            if ($char === '`') {
                $i++;
                $identifier = '';

                while ($i < $length) {
                    if ($statement[$i] === '`') {
                        if (($i + 1 < $length ? $statement[$i + 1] : '') === '`') {
                            $identifier .= '`';
                            $i += 2;

                            continue;
                        }

                        $i++;
                        break;
                    }

                    $identifier .= $statement[$i];
                    $i++;
                }

                $out .= '"'.str_replace('"', '""', $identifier).'"';

                continue;
            }

            if ($char === "'") {
                [$value, $i] = $this->readMysqlString($statement, $i);
                $out .= "'".str_replace("'", "''", $value)."'";

                continue;
            }

            $out .= $char;
            $i++;
        }

        return $out;
    }

    /**
     * Read a MySQL single-quoted string starting at $start (the opening quote),
     * decoding backslash escapes and doubled quotes. Returns [decodedValue, nextIndex].
     *
     * @return array{0: string, 1: int}
     */
    private function readMysqlString(string $sql, int $start): array
    {
        $length = strlen($sql);
        $i = $start + 1;
        $value = '';

        while ($i < $length) {
            $char = $sql[$i];

            if ($char === '\\' && $i + 1 < $length) {
                $escaped = $sql[$i + 1];
                $value .= match ($escaped) {
                    '0' => "\0",
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\x08",
                    'Z' => "\x1a",
                    '\\' => '\\',
                    "'" => "'",
                    '"' => '"',
                    default => $escaped,
                };
                $i += 2;

                continue;
            }

            if ($char === "'") {
                if (($i + 1 < $length ? $sql[$i + 1] : '') === "'") {
                    $value .= "'";
                    $i += 2;

                    continue;
                }

                $i++;
                break;
            }

            $value .= $char;
            $i++;
        }

        // Strip NUL bytes — SQLite text cannot store them.
        $value = str_replace("\0", '', $value);

        return [$value, $i];
    }

    /**
     * Split a comma-separated list at the top level (respecting parens/quotes/backticks).
     *
     * @return array<int, string>
     */
    private function splitTopLevel(string $input): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($input);
        $i = 0;

        while ($i < $length) {
            $char = $input[$i];

            if ($char === "'" || $char === '"' || $char === '`') {
                $buffer .= $char;
                $i++;

                while ($i < $length) {
                    $c = $input[$i];
                    $buffer .= $c;

                    if ($c === '\\' && $char !== '`' && $i + 1 < $length) {
                        $buffer .= $input[$i + 1];
                        $i += 2;

                        continue;
                    }

                    if ($c === $char) {
                        $i++;
                        break;
                    }

                    $i++;
                }

                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                $i++;

                continue;
            }

            $buffer .= $char;
            $i++;
        }

        if (trim($buffer) !== '') {
            $parts[] = $buffer;
        }

        return $parts;
    }

    /**
     * @return array<int, string>
     */
    private function parseColumnList(string $list): array
    {
        $columns = [];

        foreach (explode(',', $list) as $column) {
            $column = trim($column);
            $column = preg_replace('/\s+(ASC|DESC)$/i', '', $column); // drop sort direction
            $column = preg_replace('/\(\d+\)$/', '', $column);        // drop index prefix length e.g. name(191)
            $column = trim($column, " `\"");

            if ($column !== '') {
                $columns[] = $column;
            }
        }

        return $columns;
    }
}
