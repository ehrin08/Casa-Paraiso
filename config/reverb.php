<?php

$usesLocalDefaults = env('APP_ENV') === 'local';

return [

    'default' => env('REVERB_SERVER', 'reverb'),

    'servers' => [

        'reverb' => [
            'host' => env('REVERB_SERVER_HOST', '127.0.0.1'),
            'port' => (int) env('REVERB_SERVER_PORT', 8080),
            'hostname' => env('REVERB_HOST', '127.0.0.1'),
            'options' => [],
            'max_message_size' => (int) env('REVERB_MAX_MESSAGE_SIZE', 10_000),
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
                'server' => [
                    'host' => env('REVERB_SCALING_SERVER_HOST', '127.0.0.1'),
                    'port' => env('REVERB_SCALING_SERVER_PORT', 6379),
                    'username' => env('REVERB_SCALING_SERVER_USERNAME'),
                    'password' => env('REVERB_SCALING_SERVER_PASSWORD'),
                    'database' => env('REVERB_SCALING_SERVER_DB', 0),
                ],
            ],
            'pulse_ingest_interval' => (int) env('REVERB_PULSE_INGEST_INTERVAL', 15),
            'telescope_ingest_interval' => (int) env('REVERB_TELESCOPE_INGEST_INTERVAL', 15),
        ],

    ],

    'apps' => [

        'provider' => 'config',

        'apps' => [
            [
                'key' => env('REVERB_APP_KEY', $usesLocalDefaults ? 'casa-paraiso-local-key' : null),
                'secret' => env('REVERB_APP_SECRET', $usesLocalDefaults ? 'casa-paraiso-local-secret' : null),
                'app_id' => env('REVERB_APP_ID', $usesLocalDefaults ? 'casa-paraiso-local' : null),
                'options' => [
                    'host' => env('REVERB_HOST', '127.0.0.1'),
                    'port' => (int) env('REVERB_PORT', 8080),
                    'scheme' => env('REVERB_SCHEME', 'http'),
                    'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
                ],
                'allowed_origins' => array_filter(array_map('trim', explode(',', env('REVERB_ALLOWED_ORIGINS', '*')))),
                'ping_interval' => (int) env('REVERB_APP_PING_INTERVAL', 60),
                'activity_timeout' => (int) env('REVERB_APP_ACTIVITY_TIMEOUT', 30),
                'max_connections' => null,
            ],
        ],

    ],
];
