<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InactiveNavTextContrastMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_low_contrast_default_is_replaced_and_custom_colours_are_kept(): void
    {
        $migration = require database_path('settings/2026_11_05_100000_raise_inactive_nav_text_contrast.php');
        $stored = ['#9CA3AF' => '#6B7280', '#9ca3af' => '#6B7280', '#123456' => '#123456'];
        $row = fn (int $tenantId) => DB::table('settings')
            ->where(['group' => 'branding', 'name' => 'nav_text_color_inactive', 'tenant_id' => $tenantId]);

        foreach (array_keys($stored) as $tenantId => $colour) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'branding', 'name' => 'nav_text_color_inactive', 'tenant_id' => $tenantId],
                ['payload' => json_encode($colour), 'locked' => false]
            );
        }

        $migration->up();

        foreach (array_values($stored) as $tenantId => $expected) {
            $this->assertSame($expected, json_decode($row($tenantId)->value('payload')));
        }
    }
}
