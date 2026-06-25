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
];
