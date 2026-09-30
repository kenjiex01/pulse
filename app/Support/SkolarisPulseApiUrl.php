<?php

namespace App\Support;

/**
 * Normalize Skolaris Pulse API base URLs for desktop HTTP clients.
 *
 * The Skolaris web app exposes /pulse/api as a same-origin bridge; People360 must
 * call the backend pulse-api/v1 host directly.
 */
final class SkolarisPulseApiUrl
{
    public const DEFAULT_DIRECT_BASE = 'https://api-skolaris.icct.edu.ph/api/v1/pulse-api/v1';

    public static function normalize(?string $url): string
    {
        $url = rtrim(trim((string) $url), '/');

        if ($url === '') {
            return self::DEFAULT_DIRECT_BASE;
        }

        if (preg_match('#^https?://[^/]+/pulse/api$#i', $url)) {
            return self::DEFAULT_DIRECT_BASE;
        }

        return $url;
    }
}
