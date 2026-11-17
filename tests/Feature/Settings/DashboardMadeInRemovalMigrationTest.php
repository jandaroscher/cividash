<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Settings;

use App\Settings\DashboardSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardMadeInRemovalMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stored_places_are_removed_for_every_tenant_and_settings_still_load(): void
    {
        foreach ([0, 2, 5] as $tenantId) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'dashboard', 'name' => 'made_in', 'tenant_id' => $tenantId],
                ['payload' => json_encode('Demo City'), 'locked' => false]
            );
        }

        $migration = require database_path('settings/2026_11_17_100000_remove_dashboard_made_in.php');
        $migration->up();

        $this->assertSame(0, DB::table('settings')->where(['group' => 'dashboard', 'name' => 'made_in'])->count());
        app()->forgetInstance(DashboardSettings::class);
        $this->assertNotNull(app(DashboardSettings::class)->open_source_docs_url);

        $migration->down();

        $this->assertSame(1, DB::table('settings')->where(['group' => 'dashboard', 'name' => 'made_in'])->count());
    }
}
