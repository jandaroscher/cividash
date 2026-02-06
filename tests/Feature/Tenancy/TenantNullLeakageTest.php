<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\Tile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test to verify that records with NULL tenant_id are properly filtered out
 * and don't leak across tenant boundaries.
 */
class TenantNullLeakageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    /**
     * Test that records with NULL tenant_id are not visible to any tenant.
     */
    public function test_null_tenant_id_records_are_not_visible(): void
    {
        $user = \App\Models\User::factory()->create();
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        $user->tenants()->sync([$tenantA->id, $tenantB->id]);
        Filament::auth()->login($user);

        // Create a tile with NULL tenant_id (bypassing scope)
        $nullTenantTile = Tile::withoutGlobalScope('tenant')->create([
            'title' => ['de' => 'NULL Tenant Tile', 'en' => 'NULL Tenant Tile'],
            'description' => ['de' => 'desc', 'en' => 'desc'],
            'tenant_id' => null,
        ]);

        // Create tiles for tenant A and B
        Filament::setTenant($tenantA);
        $tileA = Tile::create([
            'title' => ['de' => 'Tile A', 'en' => 'Tile A'],
            'description' => ['de' => 'desc', 'en' => 'desc'],
        ]);

        Filament::setTenant($tenantB);
        $tileB = Tile::create([
            'title' => ['de' => 'Tile B', 'en' => 'Tile B'],
            'description' => ['de' => 'desc', 'en' => 'desc'],
        ]);

        // Switch to tenant A - should only see tile A, not NULL tenant tile
        Filament::setTenant($tenantA);
        $tiles = Tile::all();
        $this->assertCount(1, $tiles, 'Should only see tenant A tile, not NULL tenant tile');
        $this->assertEquals($tileA->id, $tiles->first()->id);
        $this->assertFalse($tiles->contains('id', $nullTenantTile->id), 'NULL tenant tile should not be visible');

        // Switch to tenant B - should only see tile B, not NULL tenant tile
        Filament::setTenant($tenantB);
        $tiles = Tile::all();
        $this->assertCount(1, $tiles, 'Should only see tenant B tile, not NULL tenant tile');
        $this->assertEquals($tileB->id, $tiles->first()->id);
        $this->assertFalse($tiles->contains('id', $nullTenantTile->id), 'NULL tenant tile should not be visible');
    }

    /**
     * Test that where() properly filters NULL values unlike whereBelongsTo().
     * Note: In SQL, WHERE tenant_id = ? does NOT match NULL values, so where() should work correctly.
     * However, if a default tenant exists and no tenant context is resolved, the scope might
     * fall back to the default tenant, which could cause NULL tiles to appear if they're
     * assigned to the default tenant. This test verifies that NULL tiles are properly filtered.
     */
    public function test_where_filters_null_values_correctly(): void
    {
        // Ensure no default tenant exists to avoid fallback behavior
        Tenant::where('slug', 'default')->delete();

        $user = \App\Models\User::factory()->create();
        $tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test']);

        $user->tenants()->sync([$tenant->id]);
        Filament::auth()->login($user);
        Filament::setTenant($tenant);

        // Create a tile with NULL tenant_id (bypassing scope and creating callback)
        // Use DB::table() to insert directly and bypass Eloquent callbacks
        \DB::table('tiles')->insert([
            'title' => json_encode(['de' => 'NULL Tile', 'en' => 'NULL Tile']),
            'description' => json_encode(['de' => 'desc', 'en' => 'desc']),
            'tenant_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a tile for the tenant
        $tenantTile = Tile::create([
            'title' => ['de' => 'Tenant Tile', 'en' => 'Tenant Tile'],
            'description' => ['de' => 'desc', 'en' => 'desc'],
        ]);

        // Query should only return tenant tile, not NULL tile
        // where('tenant_id', $tenant->id) should filter out NULL values
        // Note: In SQL, WHERE tenant_id = ? does NOT match NULL values, so this should work
        $tiles = Tile::all();

        // Debug: Check what tiles are returned
        $tileIds = $tiles->pluck('id')->toArray();
        $this->assertCount(1, $tiles, 'Should only see tenant tile, not NULL tenant tile. Got: '.json_encode($tileIds));
        $this->assertEquals($tenantTile->id, $tiles->first()->id);

        // Verify NULL tile exists but is filtered out
        $nullTile = Tile::withoutGlobalScope('tenant')->whereNull('tenant_id')->first();
        $this->assertNotNull($nullTile, 'NULL tenant tile should exist in database');
        $this->assertNull($nullTile->tenant_id, 'NULL tenant tile should have NULL tenant_id');
    }
}
