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
];
