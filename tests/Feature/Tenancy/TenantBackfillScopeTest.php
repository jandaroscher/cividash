<?php

namespace Tests\Feature\Tenancy;

use App\Models\Category;
use App\Models\Tenant;
use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Test to verify that the backfill command bypasses the tenant scope
 * to ensure all null tenant_id records are updated regardless of tenant context.
 */
class TenantBackfillScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_updates_all_null_tenant_id_records(): void
    {
        // Create multiple tenants
        $defaultTenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default Tenant']
        );
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        // Create records with null tenant_id (bypassing scope)
        $category1 = Category::withoutGlobalScope('tenant')->create([
            'slug' => ['de' => 'kategorie-1', 'en' => 'category-1'],
            'position' => 1,
            'tenant_id' => null,
        ]);

        $tile1 = Tile::withoutGlobalScope('tenant')->create([
            'title' => ['de' => 'Tile 1', 'en' => 'Tile 1'],
            'description' => ['de' => 'desc', 'en' => 'desc'],
            'tenant_id' => null,
        ]);

        // Create some records already assigned to different tenants
        $category2 = Category::withoutGlobalScope('tenant')->create([
            'slug' => ['de' => 'kategorie-2', 'en' => 'category-2'],
            'position' => 2,
            'tenant_id' => $tenantA->id,
        ]);

        $tile2 = Tile::withoutGlobalScope('tenant')->create([
            'title' => ['de' => 'Tile 2', 'en' => 'Tile 2'],
            'description' => ['de' => 'desc', 'en' => 'desc'],
            'tenant_id' => $tenantB->id,
        ]);

        // Run backfill - should only update null tenant_id records
        Artisan::call('tenancy:backfill', ['--default-tenant' => 'default']);

        // Verify null records were updated to default tenant
        $this->assertEquals($defaultTenant->id, $category1->fresh()->tenant_id);
        $this->assertEquals($defaultTenant->id, $tile1->fresh()->tenant_id);

        // Verify already-assigned records were not changed
        $this->assertEquals($tenantA->id, $category2->fresh()->tenant_id);
        $this->assertEquals($tenantB->id, $tile2->fresh()->tenant_id);
    }

    public function test_backfill_works_when_called_via_artisan_call(): void
    {
        // This test verifies that backfill works even when called via Artisan::call()
        // which might run in a context where the tenant scope is active

        $defaultTenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default Tenant']
        );

        // Create records with null tenant_id
        $category = Category::withoutGlobalScope('tenant')->create([
            'slug' => ['de' => 'kategorie', 'en' => 'category'],
            'position' => 1,
            'tenant_id' => null,
        ]);

        // Call backfill via Artisan::call() (simulating how TenantSeeder calls it)
        Artisan::call('tenancy:backfill', ['--default-tenant' => 'default']);

        // Verify the record was updated
        $this->assertEquals($defaultTenant->id, $category->fresh()->tenant_id);
    }
}
