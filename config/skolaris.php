<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Skolaris API connection
    |--------------------------------------------------------------------------
    |
    | People360 pulls faculty loading data from the Skolaris REST API (the same
    | endpoints used by the Skolaris frontend). Authentication uses the
    | Skolaris JWT login/refresh flow. Provide a service-account credential
    | with access to the faculty assignments overview (ideally a global /
    | super-admin account so every campus is visible).
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
    | Desktop / People360 app calls the Skolaris backend Pulse API directly (not the
    | Skolaris frontend /pulse/api bridge, which returns HTML for non-browser clients).
    */
    'pulse_api_base_url' => rtrim((string) env('SKOLARIS_PULSE_API_BASE_URL', 'https://api-skolaris.icct.edu.ph/api/v1/pulse-api/v1'), '/'),

    'pulse_api_key' => env('SKOLARIS_PULSE_API_KEY'),
];
