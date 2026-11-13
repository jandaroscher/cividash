<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NgsiLdApiUrlVersionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stored_core_url_without_version_gets_v1_and_other_urls_are_kept(): void
    {
        $migration = require database_path('settings/2026_11_12_100000_add_version_to_ngsi_ld_api_url.php');
        $stored = [
            'https://api.example.org/context/ngsi-ld' => 'https://api.example.org/context/ngsi-ld/v1',
            'https://api.example.org/context/ngsi-ld/' => 'https://api.example.org/context/ngsi-ld/v1',
            'https://api.example.org/context/ngsi-ld/v1' => 'https://api.example.org/context/ngsi-ld/v1',
            'https://core.example.org/FROST-Server/v1.1' => 'https://core.example.org/FROST-Server/v1.1',
        ];
        $row = fn (int $tenantId) => DB::table('settings')
            ->where(['group' => 'integration', 'name' => 'api_url', 'tenant_id' => $tenantId]);

        foreach (array_keys($stored) as $tenantId => $url) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'integration', 'name' => 'api_url', 'tenant_id' => $tenantId],
                ['payload' => json_encode($url), 'locked' => false]
            );
        }

        $migration->up();

        foreach (array_values($stored) as $tenantId => $expected) {
            $this->assertSame($expected, json_decode($row($tenantId)->value('payload')));
        }
    }
}
