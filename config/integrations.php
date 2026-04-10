<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CIVITAS/CORE Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to a CIVITAS/CORE urban data platform
    | instance via its NGSI-LD API. The dashboard pulls data from CORE
    | and maps it to its internal data model (tiles, categories, metrics).
    |
    */

    'civitas' => [
        'enabled' => env('CIVITAS_ENABLED', false),

        // NGSI-LD API base URL (e.g. http://localhost:9080/ngsi-ld/v1)
        'api_url' => env('CIVITAS_API_URL'),

        // OAuth2 Client Credentials for Keycloak token retrieval
        'oauth' => [
            'token_url' => env('CIVITAS_OAUTH_TOKEN_URL'),
            'client_id' => env('CIVITAS_OAUTH_CLIENT_ID'),
            'client_secret' => env('CIVITAS_OAUTH_CLIENT_SECRET'),
            'scope' => env('CIVITAS_OAUTH_SCOPE', ''),
        ],

        // Synchronisation settings
        'sync' => [
            'batch_size' => env('CIVITAS_SYNC_BATCH_SIZE', 100),
            'retry_attempts' => env('CIVITAS_SYNC_RETRY_ATTEMPTS', 3),
            'retry_delay_seconds' => env('CIVITAS_SYNC_RETRY_DELAY', 5),
            'schedule' => env('CIVITAS_SYNC_SCHEDULE', 'daily'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Keycloak SSO (Optional)
    |--------------------------------------------------------------------------
    |
    | When enabled, users can authenticate via CORE's Keycloak instance
    | using OpenID Connect. This is optional and can be toggled per tenant.
    |
    */

    'keycloak_sso' => [
        'enabled' => env('KEYCLOAK_SSO_ENABLED', false),
        'base_url' => env('KEYCLOAK_BASE_URL'),
        'realm' => env('KEYCLOAK_REALM', 'civitas'),
        'client_id' => env('KEYCLOAK_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),

        // Map Keycloak roles to dashboard roles
        'role_mapping' => [
            'admin' => 'Admin',
            'editor' => 'Redakteur',
        ],
    ],

];
