<?php

use Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromBodyParamAttribute;
use Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromBodyParamTag;
use Knuckles\Scribe\Extracting\Strategies\Headers\GetFromHeaderAttribute;
use Knuckles\Scribe\Extracting\Strategies\Headers\GetFromHeaderTag;
use Knuckles\Scribe\Extracting\Strategies\Metadata\GetFromDocBlocks;
use Knuckles\Scribe\Extracting\Strategies\Metadata\GetFromMetadataAttributes;
use Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromFormRequest;
use Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromInlineValidator;
use Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromQueryParamAttribute;
use Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromQueryParamTag;
use Knuckles\Scribe\Extracting\Strategies\ResponseFields\GetFromResponseFieldAttribute;
use Knuckles\Scribe\Extracting\Strategies\ResponseFields\GetFromResponseFieldTag;
use Knuckles\Scribe\Extracting\Strategies\Responses\ResponseCalls;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseApiResourceTags;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseAttributes;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseFileTag;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseTag;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseTransformerTags;
use Knuckles\Scribe\Extracting\Strategies\StaticData;
use Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromLaravelAPI;
use Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromUrlParamAttribute;
use Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromUrlParamTag;

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
            GetFromDocBlocks::class,
            GetFromMetadataAttributes::class,
        ],
        'headers' => [
            GetFromHeaderAttribute::class,
            GetFromHeaderTag::class,
            // StaticData with settings as tuple format
            [
                StaticData::class,
                [
                    'data' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                ],
            ],
        ],
        'urlParameters' => [
            GetFromLaravelAPI::class,
            GetFromUrlParamAttribute::class,
            GetFromUrlParamTag::class,
        ],
        'queryParameters' => [
            GetFromFormRequest::class,
            GetFromInlineValidator::class,
            GetFromQueryParamAttribute::class,
            GetFromQueryParamTag::class,
        ],
        'bodyParameters' => [
            Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromFormRequest::class,
            Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromInlineValidator::class,
            GetFromBodyParamAttribute::class,
            GetFromBodyParamTag::class,
        ],
        'responses' => [
            UseResponseAttributes::class,
            UseTransformerTags::class,
            UseApiResourceTags::class,
            UseResponseTag::class,
            UseResponseFileTag::class,
            // ResponseCalls with settings as tuple format
            [
                ResponseCalls::class,
                [
                    'only' => ['GET *'],
                    'config' => [
                        'app.debug' => false,
                    ],
                ],
            ],
        ],
        'responseFields' => [
            GetFromResponseFieldAttribute::class,
            GetFromResponseFieldTag::class,
        ],
    ],

    'database_connections_to_transact' => [config('database.default')],

    'fractal' => [
        'serializer' => null,
    ],
];
