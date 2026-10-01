<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Biometric collector logs on S3
    |--------------------------------------------------------------------------
    |
    | Attendance JSON.gz exports from biometric-collector live under
    | {prefix}/{YYYY}/{MM}/{collector_name}/{collector_name}_{stamp}.json.gzip
    | on the same backup-s3 disk as desktop DB backups (DB_BACKUP_S3_*).
    |
    */
    's3' => [
        'disk' => env('BIOMETRIC_LOGS_S3_DISK', 'backup-s3'),
        'prefix' => env('BIOMETRIC_LOGS_S3_PREFIX', 'biometric_logs'),
        'key' => env('BIOMETRIC_LOGS_S3_KEY', env('DB_BACKUP_S3_KEY')),
        'secret' => env('BIOMETRIC_LOGS_S3_SECRET', env('DB_BACKUP_S3_SECRET')),
        'region' => env('BIOMETRIC_LOGS_S3_REGION', env('DB_BACKUP_S3_REGION', 'ap-southeast-2')),
        'bucket' => env('BIOMETRIC_LOGS_S3_BUCKET', env('DB_BACKUP_S3_BUCKET')),
    ],

    'auto_pull' => [
        'interval_minutes' => (int) env('BIOMETRIC_LOGS_AUTO_PULL_INTERVAL', 5),
    ],

    /*
    | Daily email to HR Setup → HR email with campuses in dashboard “No upload today”.
    */
    'missing_upload_hr_notification' => [
        'hour' => (int) env('BIOMETRIC_MISSING_UPLOAD_HR_NOTIFICATION_HOUR', 17),
        'minute' => (int) env('BIOMETRIC_MISSING_UPLOAD_HR_NOTIFICATION_MINUTE', 0),
        'timezone' => env('BIOMETRIC_MISSING_UPLOAD_HR_NOTIFICATION_TIMEZONE', 'Asia/Manila'),
    ],
];
