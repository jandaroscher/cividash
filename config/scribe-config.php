<?php

/*
|--------------------------------------------------------------------------
| Scribe Configuration (Internal)
|--------------------------------------------------------------------------
|
| This file is loaded by config/scribe.php only when Scribe is installed.
| Do not load this file directly - it requires Scribe classes.
|
*/

use Knuckles\Scribe\Extracting\Strategies;
use Knuckles\Scribe\Config\Defaults;
use function Knuckles\Scribe\Config\configureStrategy;

return [
    'title' => 'Zukunftsbarometer API Documentation',

    'description' => 'REST API for the Zukunftsbarometer sustainability dashboard. Provides endpoints for tiles, metrics, filters, content pages, and configuration.',

    'intro_text' => <<<INTRO
        This documentation provides all the information you need to work with the Zukunftsbarometer API.

        ## Authentication

        The API has two types of endpoints:

        **Public API (GET endpoints):** No authentication required. Tenant is resolved via:
        1. Bearer Token with `tenant_id` (highest priority)
        2. Request domain matching `tenants.domain`
        3. Default tenant fallback

        **Admin API (POST/PATCH/DELETE endpoints):** Requires Bearer Token authentication with:
        - User must have `admin_api_enabled = true`
        - Token must have `admin-api` or `*` ability
        - Token must have explicit `tenant_id` (no default fallback)

        ## Tenant Isolation

        All data is tenant-scoped. Admin operations require an explicit tenant context via token or domain.
        Cross-tenant access attempts return 404 (not 403) to prevent data leakage.

        <aside>As you scroll, you'll see code examples in different programming languages in the dark area to the right.
        You can switch the language using the tabs at the top right.</aside>
    INTRO,

    'base_url' => config("app.url"),

    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/*'],
                'domains' => ['*'],
            ],
            'include' => [],
            'exclude' => [
                'GET /api/user',
            ],
        ],
    ],

    'type' => 'static',

    'theme' => 'default',

    'static' => [
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        'add_routes' => true,
        'docs_url' => '/docs',
        'assets_directory' => null,
        'middleware' => [],
    ],

    'external' => [
        'html_attributes' => []
    ],

    'try_it_out' => [
        'enabled' => true,
        'base_url' => null,
        'use_csrf' => false,
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    'auth' => [
        'enabled' => true,
        'default' => false,
        'in' => 'bearer',
        'name' => 'Authorization',
        'use_value' => env('SCRIBE_AUTH_KEY'),
        'placeholder' => '{YOUR_AUTH_TOKEN}',
        'extra_info' => <<<'AUTH'
            **Admin API** (`/api/admin/*`): Requires a Bearer token with:
            - User flag `admin_api_enabled = true`
            - Token ability `admin-api` or `*`
            - Token must have `tenant_id` set (no default fallback)

            **Public API** (GET endpoints): Authentication optional. If provided, token's `tenant_id` determines the tenant context.

            Create tokens via Filament Admin Panel: **Settings → API Keys**
        AUTH,
    ],

    'example_languages' => [
        'bash',
        'javascript',
    ],

    'postman' => [
        'enabled' => true,
        'overrides' => [],
    ],

    'openapi' => [
        'enabled' => true,
        'version' => '3.0.3',
        'overrides' => [],
        'generators' => [],
    ],

    'groups' => [
        'default' => 'Other Endpoints',
        'order' => [
            'Public API - Tiles',
            'Public API - Filters',
            'Public API - Configuration',
            'Public API - Content Pages',
            'Admin API - Tiles',
            'Admin API - Tile Years',
            'Admin API - Metric Definitions',
            'Admin API - Metric Values',
            'Admin API - Branding Configuration',
        ],
    ],

    'logo' => false,

    'last_updated' => 'Last updated: {date:F j, Y}',

    'examples' => [
        'faker_seed' => 1234,
        'models_source' => ['factoryCreate', 'factoryMake', 'databaseFirst'],
    ],

    'strategies' => [
        'metadata' => [
            ...Defaults::METADATA_STRATEGIES,
        ],
        'headers' => [
            ...Defaults::HEADERS_STRATEGIES,
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
        ],
        'urlParameters' => [
            ...Defaults::URL_PARAMETERS_STRATEGIES,
        ],
        'queryParameters' => [
            ...Defaults::QUERY_PARAMETERS_STRATEGIES,
        ],
        'bodyParameters' => [
            ...Defaults::BODY_PARAMETERS_STRATEGIES,
        ],
        'responses' => configureStrategy(
            Defaults::RESPONSES_STRATEGIES,
            Strategies\Responses\ResponseCalls::withSettings(
                only: ['GET *'],
                config: [
                    'app.debug' => false,
                ]
            )
        ),
        'responseFields' => [
            ...Defaults::RESPONSE_FIELDS_STRATEGIES,
        ]
    ],

    'database_connections_to_transact' => [config('database.default')],

    'fractal' => [
        'serializer' => null,
    ],
];
