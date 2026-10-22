<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    // Desktop builds strip AWS_* from the bundled .env (nativephp.cleanup_env_keys), so the
    // installed app needs MAIL_SES_* — otherwise it falls back to the backup-only IAM user.
    'ses' => [
        'key' => env('MAIL_SES_KEY', env('AWS_ACCESS_KEY_ID', env('DB_BACKUP_S3_KEY'))),
        'secret' => env('MAIL_SES_SECRET', env('AWS_SECRET_ACCESS_KEY', env('DB_BACKUP_S3_SECRET'))),
        'region' => env('MAIL_SES_REGION', env('AWS_DEFAULT_REGION', env('DB_BACKUP_S3_REGION', 'us-east-1'))),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
