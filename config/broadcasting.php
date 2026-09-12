<?php

$usesLocalDefaults = env('APP_ENV') === 'local';

return [

    'default' => env('BROADCAST_CONNECTION', $usesLocalDefaults ? 'reverb' : 'null'),

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY', $usesLocalDefaults ? 'casa-paraiso-local-key' : null),
            'secret' => env('REVERB_APP_SECRET', $usesLocalDefaults ? 'casa-paraiso-local-secret' : null),
            'app_id' => env('REVERB_APP_ID', $usesLocalDefaults ? 'casa-paraiso-local' : null),
            'options' => [
                'host' => env('REVERB_HOST', '127.0.0.1'),
                'port' => (int) env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
