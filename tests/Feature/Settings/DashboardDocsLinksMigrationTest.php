<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardDocsLinksMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const DOCS = 'https://github.com/jandaroscher/cividash/tree/main/docs/user-guide';

    public function test_outdated_manual_links_are_replaced_and_custom_links_are_kept(): void
    {
        $migration = require database_path('settings/2026_11_12_100000_point_dashboard_docs_links_to_repository.php');
        $stored = [
            'https://www.example.org/kontakt' => self::DOCS,
            'https://www.janda-roscher.de/kontakt' => self::DOCS,
            '' => self::DOCS,
            'javascript:alert(1)' => self::DOCS,
            'https://regensburg.example.test/handbuch' => 'https://regensburg.example.test/handbuch',
        ];
        $row = fn (int $tenantId) => DB::table('settings')
            ->where(['group' => 'dashboard', 'name' => 'user_manual_url', 'tenant_id' => $tenantId]);

        foreach (array_keys($stored) as $tenantId => $url) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'dashboard', 'name' => 'user_manual_url', 'tenant_id' => $tenantId],
                ['payload' => json_encode($url), 'locked' => false]
            );
        }
        DB::table('settings')->updateOrInsert(
            ['group' => 'dashboard', 'name' => 'user_manual_url', 'tenant_id' => 99],
            ['payload' => json_encode(null), 'locked' => false]
        );

        $migration->up();

        foreach (array_values($stored) as $tenantId => $expected) {
            $this->assertSame($expected, json_decode($row($tenantId)->value('payload')));
        }
        $this->assertSame(self::DOCS, json_decode($row(99)->value('payload')));
    }
}
