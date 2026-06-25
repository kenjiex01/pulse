<?php

return [
    /**
     * Laravel validation max (kilobytes) for uploaded files.
     * Must stay in sync with php_ini.upload_max_filesize.
     */
    'max_file_kb' => (int) env('UPLOAD_MAX_FILE_KB', 15360),

    /**
     * PHP ini directives applied by NativePHP desktop builds (ProvidesPhpIni).
     * Passed to bundled PHP via -d flags on every artisan/php process.
     */
    'php_ini' => [
        'upload_max_filesize' => env('PHP_UPLOAD_MAX_FILESIZE', '15M'),
        'post_max_size' => env('PHP_POST_MAX_SIZE', '20M'),
    ],
];
