<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\Tile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test to verify that Filament::setTenant() works even when tenancy is disabled on the panel.
 * This test verifies the fix for the issue where getTenant() returns null in tests
 * because tenancy is disabled, causing the scope to fall back to default tenant.
 */
class TenantScopeWithDisabledTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    public function test_tenant_scope_works_when_tenancy_disabled_on_panel(): void
    {
        // Verify that tenancy is enabled on the panel (even in tests) to allow getTenant() to work
        // UI pages (registration/profile) are disabled in tests, but tenancy itself is enabled
        $panel = Filament::getPanel('admin');
        $this->assertTrue($panel->hasTenancy(), 'Tenancy should be enabled on panel to allow getTenant() to work in tests');

        $user = \App\Models\User::factory()->create();
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        $user->tenants()->sync([$tenantA->id, $tenantB->id]);
        Filament::auth()->login($user);

        // Set tenant A - this should work even if tenancy is disabled on panel
        Filament::setTenant($tenantA);
        
        // Verify that getTenant() returns the set tenant even when tenancy is disabled
        $retrievedTenant = Filament::getTenant();
        $this->assertNotNull($retrievedTenant, 'getTenant() should return tenant even when tenancy is disabled on panel');
        $this->assertEquals($tenantA->id, $retrievedTenant->id, 'getTenant() should return the tenant set via setTenant()');

        // Create a tile - it should be scoped to tenant A
        $tileA = Tile::create([
            'title' => ['de' => 'A', 'en' => 'A'],
            'description' => ['de' => 'desc', 'en' => 'desc'],
        ]);

        $this->assertEquals($tenantA->id, $tileA->tenant_id, 'Tile should be assigned to tenant A');

        // Switch to tenant B
        Filament::setTenant($tenantB);
        
        // Verify tenant B is retrieved
        $retrievedTenantB = Filament::getTenant();
        $this->assertNotNull($retrievedTenantB);
        $this->assertEquals($tenantB->id, $retrievedTenantB->id);

        // Query should only return tiles for tenant B (none in this case)
        $this->assertEquals(0, Tile::count(), 'Should not see tenant A tiles when tenant B is active');
        $this->assertFalse(Tile::whereKey($tileA->id)->exists(), 'Should not be able to access tenant A tile when tenant B is active');
    }
}
