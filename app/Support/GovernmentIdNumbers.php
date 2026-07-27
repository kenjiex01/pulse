<?php

namespace App\Support;

class GovernmentIdNumbers
{
    public const TYPE_TIN = 'tin';

    public const TYPE_SSS = 'sss';

    public const TYPE_PHILHEALTH = 'philhealth';

    public const TYPE_PAGIBIG = 'pagibig';

    /**
     * @return array<int, int>
     */
    public static function allowedDigitLengths(string $type): array
    {
        return match ($type) {
            self::TYPE_SSS => [10],
            self::TYPE_PHILHEALTH => [12],
            self::TYPE_PAGIBIG => [12],
            self::TYPE_TIN => [9, 12],
            default => [],
        };
    }

    public static function maxDigits(string $type): int
    {
        return max(self::allowedDigitLengths($type) ?: [0]);
    }

    public static function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }

    public static function normalize(?string $value): ?string
    {
        if (self::isBlank($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', trim((string) $value)) ?? '';

        return $digits === '' ? null : $digits;
    }

    public static function isValid(?string $value, string $type): bool
    {
        $normalized = self::normalize($value);

        if ($normalized === null) {
            return true;
        }

        return in_array(strlen($normalized), self::allowedDigitLengths($type), true);
    }

    public static function format(?string $value, string $type): string
    {
        $digits = self::normalize($value);

        if ($digits === null) {
            return '';
        }

        return match ($type) {
            self::TYPE_SSS => self::segment($digits, [2, 7, 1]),
            self::TYPE_PHILHEALTH => self::segment($digits, [2, 9, 1]),
            self::TYPE_PAGIBIG => self::segment($digits, [4, 4, 4]),
            self::TYPE_TIN => strlen($digits) > 9
                ? self::segment($digits, [3, 3, 3, 3])
                : self::segment($digits, [3, 3, 3]),
            default => $digits,
        };
    }

    /**
     * @param  array<int, int>  $lengths
     */
    private static function segment(string $digits, array $lengths): string
    {
        $parts = [];
        $offset = 0;

        foreach ($lengths as $length) {
            $part = substr($digits, $offset, $length);

            if ($part === '') {
                break;
            }

            $parts[] = $part;
            $offset += $length;
        }

        if ($offset < strlen($digits)) {
            $parts[] = substr($digits, $offset);
        }

        return implode('-', $parts);
    }

    public static function validationMessage(string $type): string
    {
        return match ($type) {
            self::TYPE_SSS => 'The SSS number must be exactly 10 digits.',
            self::TYPE_PHILHEALTH => 'The PhilHealth number must be exactly 12 digits.',
            self::TYPE_PAGIBIG => 'The Pag-IBIG number must be exactly 12 digits.',
            self::TYPE_TIN => 'The TIN must be exactly 9 or 12 digits.',
            default => 'Invalid government ID number.',
        };
    }

    public static function uploadErrorMessage(string $type, string $label): string
    {
        return match ($type) {
            self::TYPE_SSS => "{$label} must be exactly 10 digits.",
            self::TYPE_PHILHEALTH => "{$label} must be exactly 12 digits.",
            self::TYPE_PAGIBIG => "{$label} must be exactly 12 digits.",
            self::TYPE_TIN => "{$label} must be exactly 9 or 12 digits.",
            default => "{$label} is invalid.",
        };
    }
}
