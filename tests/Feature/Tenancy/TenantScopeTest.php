<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    public function test_records_are_scoped_to_current_tenant(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        $user->tenants()->sync([$tenantA->id, $tenantB->id]);
        Filament::auth()->login($user);

        Filament::setTenant($tenantA);
        $tileA = Tile::create([
            'title' => ['de' => 'A', 'en' => 'A'],
            'description' => ['de' => 'desc', 'en' => 'desc'],
        ]);

        Filament::setTenant($tenantB);

        $this->assertEquals(0, Tile::count());
        $this->assertFalse(Tile::whereKey($tileA->id)->exists());
    }

    public function test_creating_sets_tenant_id_from_filament_context(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $user->tenants()->sync([$tenant->id]);
        Filament::auth()->login($user);
        Filament::setTenant($tenant);

        $tile = Tile::create([
            'title' => ['de' => 'A', 'en' => 'A'],
            'description' => ['de' => 'desc', 'en' => 'desc'],
        ]);

        $this->assertEquals($tenant->id, $tile->tenant_id);
    }
}
