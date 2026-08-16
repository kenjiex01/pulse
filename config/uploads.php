<?php

return [
    /**
     * Laravel validation max (kilobytes) for uploaded files.
     * Must stay in sync with php_ini.upload_max_filesize.
     */
    'max_file_kb' => (int) env('UPLOAD_MAX_FILE_KB', 15360),

    /**
     * Laravel validation max (kilobytes) for uploaded database restore (.sql) files.
     * Database dumps are large; keep at or below php_ini.upload_max_filesize / post_max_size.
     */
    'sql_restore_max_kb' => (int) env('SQL_RESTORE_MAX_KB', 262144), // 256 MB

    /**
     * PHP ini directives applied by NativePHP desktop builds (ProvidesPhpIni).
     * Passed to bundled PHP via -d flags on every artisan/php process.
     */
    'php_ini' => [
        // Sized for database restore (.sql) uploads, which are much larger than data uploads.
        'upload_max_filesize' => env('PHP_UPLOAD_MAX_FILESIZE', '256M'),
        'post_max_size' => env('PHP_POST_MAX_SIZE', '300M'),
        'memory_limit' => env('PHP_MEMORY_LIMIT', '1024M'),
        'max_execution_time' => env('PHP_MAX_EXECUTION_TIME', '0'),

        // Desktop serves pages with `php -S`, which recompiles every PHP file per request.
        // OPcache (compiled into the bundled binary) keeps them in memory for the app session.
        'opcache.enable' => env('PHP_OPCACHE_ENABLE', '1'),
        'opcache.enable_cli' => env('PHP_OPCACHE_ENABLE_CLI', '1'),
        'opcache.memory_consumption' => env('PHP_OPCACHE_MEMORY', '192'),
        'opcache.interned_strings_buffer' => env('PHP_OPCACHE_STRINGS_BUFFER', '16'),
        'opcache.max_accelerated_files' => env('PHP_OPCACHE_MAX_FILES', '20000'),
        'opcache.validate_timestamps' => env('PHP_OPCACHE_VALIDATE_TIMESTAMPS', '1'),
        'opcache.revalidate_freq' => env('PHP_OPCACHE_REVALIDATE_FREQ', '60'),
    ],
];
