<?php

return [

    /*
    | People360 desktops announce themselves on the local network so another
    | computer can list them in Database and connect to that desktop's database.
    | The same key must be used on every install. Leave the default unless you
    | set PEOPLE360_LAN_KEY to the same value on all machines.
    */
    'enabled' => env('PEOPLE360_LAN_ENABLED', true),

    'key' => env('PEOPLE360_LAN_KEY', 'people360-lan-icct'),

    'udp_port' => (int) env('PEOPLE360_LAN_UDP_PORT', 47836),

    'http_port' => (int) env('PEOPLE360_LAN_HTTP_PORT', 47837),

    'discover_timeout_seconds' => (float) env('PEOPLE360_LAN_DISCOVER_TIMEOUT', 2.5),

    'max_database_bytes' => (int) env('PEOPLE360_LAN_MAX_DATABASE_BYTES', 512 * 1024 * 1024),

];
