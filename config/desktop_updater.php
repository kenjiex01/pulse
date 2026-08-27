<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Desktop installer updates (S3)
    |--------------------------------------------------------------------------
    |
    | On desktop boot NativePHP AutoUpdater checks GitHub Releases
    | (kenjiex01/pulse). The old S3 latest.json modal is only a fallback
    | when NATIVEPHP_UPDATER_ENABLED=false.
    |
    | desktop:publish-github-release attaches EXE/ZIP/DMG + latest.yml to
    | tag v{NATIVEPHP_APP_VERSION}. After each optional S3 upload, every
    | other object under the S3 prefix is deleted (requires s3:DeleteObject).
    |
    */
    'enabled' => (bool) env('DESKTOP_INSTALLER_UPDATE_ENABLED', true),

    'disk' => 'backup-s3',

    's3_prefix' => env('DESKTOP_INSTALLER_S3_PREFIX', 'payroll_installer'),

    /** How often (minutes) to re-query S3. 0 = every desktop request (recommended). */
    'check_interval_minutes' => (int) env('DESKTOP_INSTALLER_CHECK_INTERVAL_MINUTES', 0),

    /** Pre-signed download URL lifetime (minutes). */
    'download_url_minutes' => (int) env('DESKTOP_INSTALLER_DOWNLOAD_URL_MINUTES', 60),

    /** Filename prefix for dist/ and S3 installer objects. */
    'installer_basename' => env('NATIVEPHP_INSTALLER_BASENAME', 'People360'),

    /** When a downloaded update is ready, quit and install immediately (Skolaris Desktop behavior). */
    'force_install' => (bool) env('NATIVEPHP_FORCE_UPDATE', true),

    /**
     * Local dist/ filename patterns. Capturing group 1 = semver version.
     * Used to map built files → platform before upload.
     * Accepts People360-* (current) and Pulse-* (legacy S3/local artifacts).
     */
    'artifacts' => [
        'win-x64' => '/^(?:People360|Pulse)-(.+)-setup\\.exe$/i',
        'mac-arm64' => '/^(?:People360|Pulse)-(.+)-arm64\\.dmg$/i',
        'mac-arm64-zip' => '/^(?:People360|Pulse)-(.+)-arm64\\.zip$/i',
        'mac-x64' => '/^(?:People360|Pulse)-(.+)-x64\\.dmg$/i',
        'mac-x64-zip' => '/^(?:People360|Pulse)-(.+)-x64\\.zip$/i',
    ],
];
