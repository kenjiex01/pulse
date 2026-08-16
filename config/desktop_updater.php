<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Desktop installer updates (S3)
    |--------------------------------------------------------------------------
    |
    | On desktop boot the app reads payroll_installer/latest.json. If S3 has a
    | newer NATIVEPHP_APP_VERSION, a blocking modal requires download.
    | Uses the same DB_BACKUP_S3_* credentials as cloud backup.
    |
    | Installers upload as versioned object names (Pulse-{version}-setup.exe /
    | Pulse-{version}-arm64.dmg). latest.json points at the current set.
    | After each upload, every other object under the S3 prefix is deleted so
    | only the version just published remains (requires s3:DeleteObject).
    |
    */
    'enabled' => (bool) env('DESKTOP_INSTALLER_UPDATE_ENABLED', true),

    'disk' => 'backup-s3',

    's3_prefix' => env('DESKTOP_INSTALLER_S3_PREFIX', 'payroll_installer'),

    /** How often (minutes) to re-query S3. 0 = every desktop request (recommended). */
    'check_interval_minutes' => (int) env('DESKTOP_INSTALLER_CHECK_INTERVAL_MINUTES', 0),

    /** Pre-signed download URL lifetime (minutes). */
    'download_url_minutes' => (int) env('DESKTOP_INSTALLER_DOWNLOAD_URL_MINUTES', 60),

    /**
     * Local dist/ filename patterns. Capturing group 1 = semver version.
     * Used to map built files → platform before upload.
     * S3 object keys use these versioned filenames (e.g. Pulse-0.1.48-setup.exe).
     */
    'artifacts' => [
        'win-x64' => '/^Pulse-(.+)-setup\\.exe$/i',
        'mac-arm64' => '/^Pulse-(.+)-arm64\\.dmg$/i',
        'mac-x64' => '/^Pulse-(.+)-x64\\.dmg$/i',
    ],
];
