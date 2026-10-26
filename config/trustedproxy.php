<?php

return [
    // Read after configuration loads, including on cached HTTP/Artisan boots.
    'proxies' => env('TRUSTED_PROXIES', '127.0.0.1,172.16.0.0/12'),
];
