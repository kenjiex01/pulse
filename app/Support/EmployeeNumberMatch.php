<?php

namespace App\Support;

final class EmployeeNumberMatch
{
    public static function normalize(?string $value): string
    {
        return strtoupper(trim(preg_replace('/[\s\-]+/', '', (string) $value) ?? ''));
    }

    public static function same(?string $left, ?string $right): bool
    {
        $a = self::normalize($left);
        $b = self::normalize($right);

        return $a !== '' && $a === $b;
    }

    /**
     * @return list<string>
     */
    public static function splitSectionTokens(?string $section): array
    {
        $section = trim((string) $section);

        if ($section === '' || $section === '—') {
            return [];
        }

        $parts = preg_split('/\s*\/\s*/', $section) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            $token = strtoupper(trim(preg_replace('/\s+/', '', $part) ?? ''));

            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        return array_values(array_unique($tokens));
    }
}
