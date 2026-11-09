<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://buyer.lumearofficial.com',
        'https://logistics.lumearofficial.com',
        'https://rider.lumearofficial.com',
        'http://localhost:3000',
        'http://localhost:5173',
    ],
    // Flutter web chooses a development port dynamically. Native Android and
    // Windows builds do not use browser CORS, but this lets Chrome/Edge test
    // builds use the same shared Laravel API without opening it to all sites.
    'allowed_origins_patterns' => [
        '#^https?://(localhost|127\\.0\\.0\\.1)(:\\d+)?$#',
    ],
    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
