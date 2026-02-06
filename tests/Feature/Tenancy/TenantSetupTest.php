<?php

namespace Tests\Feature\Tenancy;

use App\Models\Category;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_schema_exists(): void
    {
        $this->assertTrue(Schema::hasTable('tenants'));
        $this->assertTrue(Schema::hasTable('tenant_user'));

        $this->assertTrue(Schema::hasColumns('tenants', [
            'id',
            'name',
            'slug',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('tenant_user', [
            'tenant_id',
            'user_id',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_backfill_creates_default_tenant_and_assigns_existing_records(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'slug' => ['de' => 'kategorie', 'en' => 'category'],
            'position' => 1,
        ]);
        $tile = Tile::create([
            'title' => ['de' => 'Tile', 'en' => 'Tile'],
            'description' => ['de' => 'Beschreibung', 'en' => 'Description'],
        ]);

        Artisan::call('tenancy:backfill');

        $defaultTenant = Tenant::where('slug', 'default')->first();

        $this->assertNotNull($defaultTenant);
        $this->assertTrue($user->tenants()->whereKey($defaultTenant->id)->exists());

        $this->assertEquals($defaultTenant->id, $category->fresh()->tenant_id);
        $this->assertEquals($defaultTenant->id, $tile->fresh()->tenant_id);
    }

    public function test_tenant_seeder_creates_demo_tenant_and_links_user(): void
    {
        // Create user with demo email so seeder can find it
        $user = User::factory()->create([
            'email' => 'demo@example.com',
        ]);

        $this->seed(TenantSeeder::class);

        $tenant = Tenant::where('slug', 'stadt-regensburg')->first();

        $this->assertNotNull($tenant);
        $this->assertTrue($user->fresh()->tenants()->whereKey($tenant->id)->exists());
    }
}
