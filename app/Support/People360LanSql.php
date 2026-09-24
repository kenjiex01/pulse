<?php

namespace App\Support;

class People360LanSql
{
    /**
     * @param  array<int|string, mixed>  $bindings
     * @return array<int|string, array{t: string, v?: mixed}>
     */
    public static function encode(array $bindings): array
    {
        $encoded = [];

        foreach ($bindings as $key => $value) {
            $encoded[$key] = self::encodeValue($value);
        }

        return $encoded;
    }

    /**
     * @param  array<int|string, mixed>  $bindings
     * @return array<int|string, mixed>
     */
    public static function decode(array $bindings): array
    {
        $decoded = [];

        foreach ($bindings as $key => $value) {
            $decoded[$key] = is_array($value) ? self::decodeValue($value) : $value;
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        $indexes = [];
        foreach (array_keys($decoded) as $key) {
            if (! is_numeric($key)) {
                return $decoded;
            }
            $indexes[] = (int) $key;
        }

        if ($indexes === range(1, count($decoded))) {
            return array_values($decoded);
        }

        return $decoded;
    }

    /**
     * @return array{t: string, v?: mixed}
     */
    private static function encodeValue(mixed $value): array
    {
        if ($value === null) {
            return ['t' => 'null'];
        }

        if (is_int($value)) {
            return ['t' => 'int', 'v' => $value];
        }

        if (is_float($value)) {
            return ['t' => 'float', 'v' => $value];
        }

        if (is_bool($value)) {
            return ['t' => 'int', 'v' => $value ? 1 : 0];
        }

        $string = (string) $value;
        if (! mb_check_encoding($string, 'UTF-8')) {
            return ['t' => 'blob', 'v' => base64_encode($string)];
        }

        return ['t' => 'str', 'v' => $string];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private static function decodeValue(array $value): mixed
    {
        return match ($value['t'] ?? 'str') {
            'null' => null,
            'int' => (int) ($value['v'] ?? 0),
            'float' => (float) ($value['v'] ?? 0),
            'blob' => base64_decode((string) ($value['v'] ?? ''), true) ?: '',
            default => (string) ($value['v'] ?? ''),
        };
    }
}
