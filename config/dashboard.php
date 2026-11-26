<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

return [

    /*
    |--------------------------------------------------------------------------
    | Demo reset schedule
    |--------------------------------------------------------------------------
    |
    | Controls whether the nightly `dashboard:reset --force` schedule (see
    | routes/console.php) is registered. This command re-activates the demo
    | admin accounts (test@example.com, demo@example.com) and wipes the
    | default tenant's content, so it must stay opt-in and only be enabled
    | on demo/reset instances, never on a real production install.
    |
    */

    'demo_reset' => env('DASHBOARD_DEMO_RESET', false),

    /*
    |--------------------------------------------------------------------------
    | Seeded admin password
    |--------------------------------------------------------------------------
    |
    | Password for the seeded admin accounts (test@example.com, demo@example.com).
    | If unset, the seeders generate a random password instead. Read through
    | config (not env() directly) so it still works when config is cached.
    |
    */

    'seed_admin_password' => env('SEED_ADMIN_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Public API rate limit
    |--------------------------------------------------------------------------
    |
    | Requests per minute and client IP for the public read endpoints (tiles,
    | filters, config, content pages). One page view in the SPA triggers about
    | ten of these requests, and visitors behind a shared network (public
    | administration, companies, schools) share one IP, so the limit has to
    | cover many page views per minute.
    |
    */

    // An empty or zero value would reject every public request, so fall back to the default.
    'public_api_rate_limit' => (int) env('PUBLIC_API_RATE_LIMIT') ?: 300,

];
