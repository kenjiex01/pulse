<?php

namespace App\Support;

/**
 * Skolaris JWT credentials for People360 (Employee Load template source).
 *
 * These are the same values as the Skolaris web login (skolaris-fe authService.login → POST /api/v1/login):
 * - identifier: user email or username (AuthController::login — not student number for staff)
 * - password: the account password
 *
 * This is separate from People360 Pulse API keys (skp_… from Skolaris Admin → Pulse API Management).
 * Use a Skolaris admin / registrar service account with global access to enrollment periods and faculty loading
 * (e.g. Super Admin), not a People360-only pulse_workspace login.
 */
final class SkolarisJwtCredentials
{
    public static function isConfigured(): bool
    {
        return trim((string) config('skolaris.identifier')) !== ''
            && trim((string) config('skolaris.password')) !== '';
    }

    /**
     * @return array{identifier: string, password: string}
     */
    public static function loginPayload(): array
    {
        return [
            'identifier' => trim((string) config('skolaris.identifier')),
            'password' => (string) config('skolaris.password'),
        ];
    }
}
