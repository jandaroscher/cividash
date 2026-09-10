<?php

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

];
