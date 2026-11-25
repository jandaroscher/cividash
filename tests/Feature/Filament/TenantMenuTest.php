<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Filament;

use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantMenuTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::firstOrCreate(['slug' => 'default'], ['name' => 'Default Dashboard']);
        $this->tenant->update(['description' => 'Climate targets of the city']);
    }

    public function test_editor_with_one_dashboard_sees_a_plain_label_with_its_description(): void
    {
        $editor = User::factory()->create(['is_admin' => false]);
        $editor->tenants()->sync([$this->tenant->id]);

        $html = $this->adminPageFor($editor);

        $this->assertStringContainsString('cd-tenant-menu-trigger-static', $html);
        $this->assertStringContainsString('Climate targets of the city', $html);
    }

    public function test_admin_with_two_dashboards_can_open_the_menu_and_sees_descriptions(): void
    {
        $other = Tenant::create(['slug' => 'demo-city', 'name' => 'Demo City', 'description' => 'Second dashboard']);
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->tenants()->sync([$this->tenant->id, $other->id]);

        $html = $this->adminPageFor($admin);

        $this->assertStringNotContainsString('cd-tenant-menu-trigger-static', $html);
        $this->assertStringContainsString('Second dashboard', $html);
    }

    public function test_searchable_menu_with_many_dashboards_still_shows_descriptions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tenantIds = [$this->tenant->id];
        foreach (range(1, 10) as $i) {
            $tenantIds[] = Tenant::create(['slug' => "city-{$i}", 'name' => "City {$i}", 'description' => "Dashboard number {$i}"])->id;
        }
        $admin->tenants()->sync($tenantIds);

        $html = $this->adminPageFor($admin);

        $this->assertStringContainsString('x-model="search"', $html);
        $this->assertStringContainsString('Dashboard number 7', $html);
    }

    private function adminPageFor(User $user): string
    {
        $this->actingAs($user);
        Filament::auth()->login($user);
        Filament::setTenant($this->tenant);

        $response = $this->followingRedirects()->get('/admin');
        $response->assertOk();

        return $response->getContent();
    }
}
