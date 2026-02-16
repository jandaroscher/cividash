<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dashboard JSON Seeding Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for seeding Tiles and Categories from dashboard.json
    |
    */

    'default_json_path' => env('SEED_DASHBOARD_JSON', storage_path('app/seeds/regensburg/dashboard.json')),

    'dashboard_json_url' => env('SEED_DASHBOARD_JSON_URL'),

    /*
    |--------------------------------------------------------------------------
    | JSON Structure Keys
    |--------------------------------------------------------------------------
    |
    | Define the keys used in the dashboard.json file structure
    |
    */
    'json_keys' => [
        'tiles' => 'kacheln',
        'categories' => 'handlungsfelder',
        'tile_category_links' => 'lnk_handlungsfelder',
        'metrics' => 'Kennzahlen',
        'data' => 'daten',
        'sdg' => 'sdg',
        'dimensions' => 'dimensions', // Not in JSON, but for consistency
    ],

    /*
    |--------------------------------------------------------------------------
    | Block Transformation Settings
    |--------------------------------------------------------------------------
    |
    | Settings for transforming JSON content into Fabricator blocks
    |
    */
    'block_transformation' => [
        'default_block_type' => 'intro-text',
        'slider_block_type' => 'slider',
        'text_image_block_type' => 'text-image',
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Download Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for downloading media files during seeding. The base URL
    | is the source site root (files under /files, assets under /assets).
    | Without it, seeding skips media downloads.
    |
    */
    'media_download_enabled' => env('SEED_MEDIA_DOWNLOAD', true),
    'media_base_url' => env('SEED_MEDIA_BASE_URL'),
    'media_storage_path' => env('SEED_MEDIA_STORAGE_PATH', 'seeds'),
];
