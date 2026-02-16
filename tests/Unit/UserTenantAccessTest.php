<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTenantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_access_tenant_with_loaded_relation(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::where('slug', 'default')->first();

        // Explicitly load the tenants relation
        $user->load('tenants');

        $this->assertTrue($user->relationLoaded('tenants'));
        $this->assertTrue($user->canAccessTenant($tenant));
    }

    public function test_can_access_tenant_without_loaded_relation(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::where('slug', 'default')->first();

        // Get a fresh instance without loaded relations
        $freshUser = User::find($user->id);

        $this->assertFalse($freshUser->relationLoaded('tenants'));
        $this->assertTrue($freshUser->canAccessTenant($tenant));
    }

    public function test_cannot_access_unlinked_tenant(): void
    {
        $user = User::factory()->create();
        $unlinkedTenant = Tenant::create(['name' => 'Unlinked', 'slug' => 'unlinked']);

        $this->assertFalse($user->canAccessTenant($unlinkedTenant));
    }

    public function test_cannot_access_unlinked_tenant_with_loaded_relation(): void
    {
        $user = User::factory()->create();
        $unlinkedTenant = Tenant::create(['name' => 'Unlinked', 'slug' => 'unlinked']);

        $user->load('tenants');

        $this->assertTrue($user->relationLoaded('tenants'));
        $this->assertFalse($user->canAccessTenant($unlinkedTenant));
    }

    public function test_get_default_tenant_returns_configured_default(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);

        $user->tenants()->syncWithoutDetaching([$tenantA->id, $tenantB->id]);
        $user->forceFill(['default_tenant_id' => $tenantB->id])->save();
        $user->refresh();

        $panel = app(Panel::class);

        $default = $user->getDefaultTenant($panel);

        $this->assertNotNull($default);
        $this->assertEquals($tenantB->id, $default->id);
    }

    public function test_get_default_tenant_falls_back_to_first(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::create(['name' => 'Alpha', 'slug' => 'alpha']);
        $tenantB = Tenant::create(['name' => 'Beta', 'slug' => 'beta']);

        // Attach only tenantA and tenantB (default tenant is auto-attached)
        $user->tenants()->syncWithoutDetaching([$tenantA->id, $tenantB->id]);

        // Point default to a tenant the user does NOT have access to
        $inaccessibleTenant = Tenant::create(['name' => 'Inaccessible', 'slug' => 'inaccessible']);
        $user->forceFill(['default_tenant_id' => $inaccessibleTenant->id])->save();
        $user->refresh();

        $panel = app(Panel::class);

        $default = $user->getDefaultTenant($panel);

        // Should fall back to the first accessible tenant (ordered by name)
        $this->assertNotNull($default);
        $this->assertNotEquals($inaccessibleTenant->id, $default->id);

        // The first tenant by name should be returned
        $firstAccessible = $user->tenants()->orderBy('name')->first();
        $this->assertEquals($firstAccessible->id, $default->id);
    }

    public function test_get_default_tenant_returns_null_when_no_tenants(): void
    {
        $user = User::factory()->create();

        // Detach all tenants including auto-attached default
        $user->tenants()->detach();
        $user->forceFill(['default_tenant_id' => null])->save();
        $user->refresh();

        $panel = app(Panel::class);

        $default = $user->getDefaultTenant($panel);

        $this->assertNull($default);
    }

    public function test_user_auto_attaches_to_default_tenant_on_creation(): void
    {
        $user = User::factory()->create();
        $defaultTenant = Tenant::where('slug', 'default')->first();

        $this->assertTrue($user->tenants->contains($defaultTenant));
        $this->assertEquals($defaultTenant->id, $user->default_tenant_id);
    }
}
