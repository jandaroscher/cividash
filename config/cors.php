<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie', // if you use Sanctum
    ],

    'allowed_methods' => ['*'],

    //
    // Replace the '*' here with the exact origin of your front-end
    // when you have 'supports_credentials' => true
    //
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    //
    // If you are sending cookies or auth headers, set this to true:
    //
    'supports_credentials' => true,
];
