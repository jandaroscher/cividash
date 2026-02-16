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
    'allowed_origins' => array_filter([
        env('FRONTEND_URL'),
    ]),

    'allowed_origins_patterns' => array_filter([
        (function () {
            $host = parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST);
            if (! $host) {
                return null;
            }
            $escaped = preg_quote($host, '#');

            return '#^https?://([a-z0-9-]+\.)?'.$escaped.'$#';
        })(),
    ]),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    //
    // If you are sending cookies or auth headers, set this to true:
    //
    'supports_credentials' => true,
];
