<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    /**
     * Test that tenant scope prevents data leakage when no tenant context is available.
     * This verifies the fix for the issue where API requests without tenant context
     * would expose data from all tenants.
     */
    public function test_scope_prevents_data_leakage_without_tenant_context(): void
    {
        // Create default tenant first
        $defaultTenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default Tenant']
        );

        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        // Create tiles for different tenants (bypassing scope)
        Tile::withoutGlobalScope('tenant')->create([
            'title' => ['de' => 'Tile A', 'en' => 'Tile A'],
            'description' => ['de' => 'desc', 'en' => 'desc'],
            'tenant_id' => $tenantA->id,
        ]);

        Tile::withoutGlobalScope('tenant')->create([
            'title' => ['de' => 'Tile B', 'en' => 'Tile B'],
            'description' => ['de' => 'desc', 'en' => 'desc'],
            'tenant_id' => $tenantB->id,
        ]);

        Tile::withoutGlobalScope('tenant')->create([
            'title' => ['de' => 'Tile Default', 'en' => 'Tile Default'],
            'description' => ['de' => 'desc', 'en' => 'desc'],
            'tenant_id' => $defaultTenant->id,
        ]);

        // In test context, the scope should still apply (we modified it to not skip tests)
        // Without tenant context, should only see default tenant's data
        $this->assertEquals(1, Tile::count());
        $this->assertEquals('Tile Default', Tile::first()->getTranslation('title', 'en'));
        
        // Verify that other tenants' data is not visible
        $this->assertFalse(Tile::where('tenant_id', $tenantA->id)->exists());
        $this->assertFalse(Tile::where('tenant_id', $tenantB->id)->exists());
    }

    /**
     * Test that tenant can be resolved from query parameter in API requests.
     * Note: This test verifies the resolveTenant() logic works correctly.
     * Full integration testing would require actual HTTP requests.
     */
    public function test_tenant_resolved_from_query_parameter(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        
        // Link user to tenant so they have access
        $user->tenants()->sync([$tenantA->id]);
        
        // Authenticate user
        $this->actingAs($user);
        
        // Verify resolveTenant can find tenant by slug
        // In real API requests, this would be called via the scope
        $reflection = new \ReflectionClass(\App\Models\Tile::class);
        $method = $reflection->getMethod('resolveTenant');
        $method->setAccessible(true);
        
        // Create a mock request with tenant parameter
        $request = \Illuminate\Http\Request::create('/api/tiles?tenant=tenant-a', 'GET');
        $this->app->instance('request', $request);
        
        // Invoke resolveTenant via reflection
        $resolvedTenant = $method->invoke(null);
        
        // Assert that the resolved tenant matches the expected tenant
        $this->assertNotNull($resolvedTenant);
        $this->assertEquals($tenantA->id, $resolvedTenant->id);
    }

    /**
     * Test that tenant can be resolved from header in API requests.
     * Note: This test verifies the resolveTenant() logic works correctly.
     */
    public function test_tenant_resolved_from_header(): void
    {
        $user = User::factory()->create();
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);
        
        // Link user to tenant so they have access
        $user->tenants()->sync([$tenantB->id]);
        
        // Authenticate user
        $this->actingAs($user);
        
        // Get resolveTenant method via reflection
        $reflection = new \ReflectionClass(\App\Models\Tile::class);
        $method = $reflection->getMethod('resolveTenant');
        $method->setAccessible(true);
        
        // Create a mock request with tenant header
        $request = \Illuminate\Http\Request::create('/api/tiles', 'GET');
        $request->headers->set('X-Tenant', 'tenant-b');
        $this->app->instance('request', $request);
        
        // Invoke resolveTenant via reflection
        $resolvedTenant = $method->invoke(null);
        
        // Assert that the resolved tenant matches the expected tenant
        $this->assertNotNull($resolvedTenant);
        $this->assertEquals($tenantB->id, $resolvedTenant->id);
    }

    /**
     * Test that console commands bypass the tenant scope.
     */
    public function test_console_commands_bypass_tenant_scope(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        // Create tiles for different tenants
        Tile::withoutGlobalScope('tenant')->create([
            'title' => ['de' => 'Tile A', 'en' => 'Tile A'],
            'description' => ['de' => 'desc', 'en' => 'desc'],
            'tenant_id' => $tenantA->id,
        ]);

        Tile::withoutGlobalScope('tenant')->create([
            'title' => ['de' => 'Tile B', 'en' => 'Tile B'],
            'description' => ['de' => 'desc', 'en' => 'desc'],
            'tenant_id' => $tenantB->id,
        ]);

        // Console context should bypass tenant scope - verify all tiles are visible
        // when running in console mode
        // This is tested indirectly by the backfill command which uses withoutGlobalScope
        $this->markTestIncomplete('TODO: Implement console scope bypass verification');
    }
}
