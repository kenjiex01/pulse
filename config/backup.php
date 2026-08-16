<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Local backup retention
    |--------------------------------------------------------------------------
    |
    | Copies are also stored under storage/app/backups. Set to 0 to skip
    | keeping local copies (download-only).
    |
    */
    'keep_copies' => (int) env('DB_BACKUP_KEEP_COPIES', 10),

    /*
    |--------------------------------------------------------------------------
    | Tables omitted from downloadable SQL backups
    |--------------------------------------------------------------------------
    |
    | Module navigation and role-module pivots come from the app version (seeders).
    | Restoring an older dump with stale route_name values causes 404s after upgrade.
    | These tables are also stripped from uploaded restore files, then re-seeded.
    |
    */
    'exclude_tables_from_dump' => [
        'tbl_modules',
        'sys_sub_modules',
        'tbl_role_modules',
        'tbl_role_sub_modules',
    ],

    /*
    |--------------------------------------------------------------------------
    | Desktop cloud backup (S3)
    |--------------------------------------------------------------------------
    |
    | Daily backup for desktop and browser. Uses DB_BACKUP_S3_* keys so
    | credentials survive desktop packaging (nativephp strips generic AWS_*).
    | Objects are stored as {s3_prefix}/{YYYY}/{MM}/{filename}.sql.gz.
    |
    */
    'cloud' => [
        'enabled' => (bool) env('DB_BACKUP_CLOUD_ENABLED', false),
        'disk' => 'backup-s3',
        'timezone' => env('DB_BACKUP_CLOUD_TIMEZONE', 'Asia/Manila'),
        'schedule_hour' => (int) env('DB_BACKUP_CLOUD_SCHEDULE_HOUR', 10),
        'schedule_minute' => (int) env('DB_BACKUP_CLOUD_SCHEDULE_MINUTE', 0),
        's3_prefix' => env('DB_BACKUP_CLOUD_S3_PREFIX', 'payroll-backups'),
        'keep_local_gzip' => (bool) env('DB_BACKUP_CLOUD_KEEP_LOCAL_GZIP', false),
        'key' => env('DB_BACKUP_S3_KEY'),
        'secret' => env('DB_BACKUP_S3_SECRET'),
        'region' => env('DB_BACKUP_S3_REGION', 'ap-southeast-1'),
        'bucket' => env('DB_BACKUP_S3_BUCKET'),
    ],
];
