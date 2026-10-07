<?php

namespace App\Support;

/**
 * Match People360 employee names to Skolaris uploaded-faculty-load item names.
 */
final class FacultyNameMatcher
{
    public static function namesLikelySame(?string $left, ?string $right): bool
    {
        $leftTokens = self::significantTokens($left);
        $rightTokens = self::significantTokens($right);

        if ($leftTokens === [] || $rightTokens === []) {
            return false;
        }

        $shared = count(array_intersect($leftTokens, $rightTokens));
        $required = min(2, min(count($leftTokens), count($rightTokens)));

        return $shared >= $required;
    }

    /**
     * @return array<int, string>
     */
    private static function significantTokens(?string $name): array
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z\s]/', ' ', (string) $name) ?? '');
        $parts = preg_split('/\s+/', trim($normalized)) ?: [];

        $tokens = [];

        foreach ($parts as $part) {
            if (strlen($part) >= 2) {
                $tokens[] = $part;
            }
        }

        return array_values(array_unique($tokens));
    }
}
