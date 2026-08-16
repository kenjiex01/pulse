<?php

/**
 * NativePHP desktop build secrets (local .env only — stripped from shipped app).
 */
return [
    'notarization' => [
        'enabled' => filter_var(env('NATIVEPHP_NOTARIZE', false), FILTER_VALIDATE_BOOLEAN),
        'apple_id' => env('NATIVEPHP_APPLE_ID'),
        'apple_id_pass' => env('NATIVEPHP_APPLE_ID_PASS'),
        'apple_team_id' => env('NATIVEPHP_APPLE_TEAM_ID'),
    ],

    'mac_signing' => [
        /** Full name, e.g. "Developer ID Application: ICCT Colleges (XXXXXXXXXX)" */
        'identity' => env('NATIVEPHP_MAC_IDENTITY'),
        'csc_link' => env('CSC_LINK'),
        'csc_key_password' => env('CSC_KEY_PASSWORD'),
    ],

    'azure_trusted_signing' => [
        'tenant_id' => env('AZURE_TENANT_ID'),
        'client_id' => env('AZURE_CLIENT_ID'),
        'client_secret' => env('AZURE_CLIENT_SECRET'),
        'publisher_name' => env('NATIVEPHP_AZURE_PUBLISHER_NAME'),
        'endpoint' => env('NATIVEPHP_AZURE_ENDPOINT'),
        'certificate_profile_name' => env('NATIVEPHP_AZURE_CERTIFICATE_PROFILE_NAME'),
        'code_signing_account_name' => env('NATIVEPHP_AZURE_CODE_SIGNING_ACCOUNT_NAME'),
    ],
];
