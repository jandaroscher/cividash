<?php

// Only the most common configs are shown. See the https://scribe.knuckles.wtf/laravel/reference/config for all.

return [
    // The HTML <title> for the generated documentation.
    'title' => 'CiviDash API Documentation',

    // A short description of your API. Will be included in the docs webpage, Postman collection and OpenAPI spec.
    'description' => 'REST API for CiviDash. Provides endpoints for tiles, metrics, filters, content pages, and configuration.',

    // Text to place in the "Introduction" section, right after the `description`. Markdown and HTML are supported.
    'intro_text' => <<<'INTRO'
        This documentation provides all the information you need to work with the CiviDash API.

        ## Authentication

        The API has two types of endpoints:

        **Public API (GET endpoints):** No authentication required. Tenant is resolved via:
        1. Bearer Token with `tenant_id` (highest priority)
        2. Request domain matching `tenants.domain`
        3. Default tenant fallback

        **Admin API (POST/PATCH/DELETE endpoints):** Requires Bearer Token authentication with:
        - Token must have `admin-api` or `*` ability
        - Token must have explicit `tenant_id` (no default fallback)

        ## Tenant Isolation

        All data is tenant-scoped. Admin operations require an explicit tenant context via token or domain.
        Cross-tenant access attempts return 404 (not 403) to prevent data leakage.

        <aside>As you scroll, you'll see code examples in different programming languages in the dark area to the right.
        You can switch the language using the tabs at the top right.</aside>
    INTRO,

    // The base URL displayed in the docs.
    'base_url' => config('app.url'),

    // Routes to include in the docs
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

    // Using "external_static" with Scalar for modern interactive API docs
    'type' => 'external_static',

    'theme' => 'scalar',

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
        'html_attributes' => [],
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
            'Public API - Categories',
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
            \Knuckles\Scribe\Extracting\Strategies\Metadata\GetFromDocBlocks::class,
            \Knuckles\Scribe\Extracting\Strategies\Metadata\GetFromMetadataAttributes::class,
        ],
        'headers' => [
            \Knuckles\Scribe\Extracting\Strategies\Headers\GetFromHeaderAttribute::class,
            \Knuckles\Scribe\Extracting\Strategies\Headers\GetFromHeaderTag::class,
            // StaticData with settings as tuple format
            [
                \Knuckles\Scribe\Extracting\Strategies\StaticData::class,
                [
                    'data' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                ],
            ],
        ],
        'urlParameters' => [
            \Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromLaravelAPI::class,
            \Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromUrlParamAttribute::class,
            \Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromUrlParamTag::class,
        ],
        'queryParameters' => [
            \Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromFormRequest::class,
            \Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromInlineValidator::class,
            \Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromQueryParamAttribute::class,
            \Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromQueryParamTag::class,
        ],
        'bodyParameters' => [
            \Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromFormRequest::class,
            \Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromInlineValidator::class,
            \Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromBodyParamAttribute::class,
            \Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromBodyParamTag::class,
        ],
        'responses' => [
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseAttributes::class,
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseTransformerTags::class,
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseApiResourceTags::class,
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseTag::class,
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseFileTag::class,
            // ResponseCalls with settings as tuple format
            [
                \Knuckles\Scribe\Extracting\Strategies\Responses\ResponseCalls::class,
                [
                    'only' => ['GET *'],
                    'config' => [
                        'app.debug' => false,
                    ],
                ],
            ],
        ],
        'responseFields' => [
            \Knuckles\Scribe\Extracting\Strategies\ResponseFields\GetFromResponseFieldAttribute::class,
            \Knuckles\Scribe\Extracting\Strategies\ResponseFields\GetFromResponseFieldTag::class,
        ],
    ],

    'database_connections_to_transact' => [config('database.default')],

    'fractal' => [
        'serializer' => null,
    ],
];
