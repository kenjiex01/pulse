<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LibreOffice path (optional)
    |--------------------------------------------------------------------------
    |
    | Used to convert complex Office files (.doc with charts/images) to PDF
    | for in-app preview. Leave empty to auto-detect portable runtime, then
    | common system install locations.
    |
    */

    'libreoffice_path' => env('LIBREOFFICE_PATH', ''),

    'libreoffice_timeout' => (int) env('LIBREOFFICE_TIMEOUT', 90),

    /*
    |--------------------------------------------------------------------------
    | Optional portable LibreOffice runtime (first-use download)
    |--------------------------------------------------------------------------
    |
    | When Office preview needs charts/images and no LibreOffice binary is
    | found, Pulse can download a portable runtime into Laravel storage
    | (~280–360 MB once). Works for browser and NativePHP desktop.
    |
    */

    'runtime_enabled' => (bool) env('LIBREOFFICE_RUNTIME_DOWNLOAD', true),

    'runtime_version' => env('LIBREOFFICE_RUNTIME_VERSION', '26.2.5'),

    'runtime_directory' => 'libreoffice-runtime',

    'runtime_downloads' => [
        'mac-arm64' => 'https://download.documentfoundation.org/libreoffice/stable/{version}/mac/aarch64/LibreOffice_{version}_MacOS_aarch64.dmg',
        'mac-x64' => 'https://download.documentfoundation.org/libreoffice/stable/{version}/mac/x86_64/LibreOffice_{version}_MacOS_x86-64.dmg',
        'win-x64' => 'https://download.documentfoundation.org/libreoffice/stable/{version}/win/x86_64/LibreOffice_{version}_Win_x86-64.msi',
    ],

];
