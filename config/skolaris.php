<?php

use App\Support\SkolarisPulseApiUrl;

return [
    /*
    |--------------------------------------------------------------------------
    | Skolaris API connection
    |--------------------------------------------------------------------------
    |
    | Employee Load uses the same JWT login as Skolaris web (POST /api/v1/login with
    | identifier + password). Set SKOLARIS_API_IDENTIFIER to the service account email
    | or username and SKOLARIS_API_PASSWORD to its password. This is separate from
    | SKOLARIS_PULSE_API_KEY (skp_…). Prefer a global admin for all campuses.
    |
    */

    'base_url' => rtrim((string) env('SKOLARIS_API_BASE_URL', 'https://api-skolaris.icct.edu.ph/api/v1'), '/'),

    'identifier' => env('SKOLARIS_API_IDENTIFIER'),

    'password' => env('SKOLARIS_API_PASSWORD'),

    // Optional pre-seeded refresh token to avoid an initial /login call.
    'refresh_token' => env('SKOLARIS_API_REFRESH_TOKEN'),

    // HTTP request timeout in seconds.
    'timeout' => (int) env('SKOLARIS_API_TIMEOUT', 30),

    // Cache TTL (minutes) for the access token. Skolaris access tokens live
    // for 1 hour; keep a small safety margin.
    'token_ttl_minutes' => (int) env('SKOLARIS_API_TOKEN_TTL', 55),

    /*
    | Desktop / People360 may store the Skolaris frontend bridge URL
    | (https://skolaris.icct.edu.ph/pulse/api); it is rewritten to the direct API host at boot.
    */
    'pulse_api_base_url' => SkolarisPulseApiUrl::normalize(env('SKOLARIS_PULSE_API_BASE_URL')),

    'pulse_api_key' => env('SKOLARIS_PULSE_API_KEY'),
];
