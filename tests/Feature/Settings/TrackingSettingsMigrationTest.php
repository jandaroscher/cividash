<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrackingSettingsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollback_removes_the_matomo_values_of_every_tenant(): void
    {
        DB::table('settings')->updateOrInsert(
            ['group' => 'tracking', 'name' => 'matomo_url', 'tenant_id' => 5],
            ['payload' => json_encode('https://matomo.example.org/'), 'locked' => false]
        );

        $migration = require database_path('settings/2026_11_26_100000_create_tracking_settings.php');
        $migration->down();

        $this->assertSame(0, DB::table('settings')->where('group', 'tracking')->count());
    }
}
