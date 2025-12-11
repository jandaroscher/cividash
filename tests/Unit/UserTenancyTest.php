<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_tenants_returns_linked_tenants(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);

        $user->tenants()->sync([$tenantA->id, $tenantB->id]);

        $panel = app(Panel::class);

        $tenants = $user->getTenants($panel);

        $this->assertCount(2, $tenants);
        $this->assertEqualsCanonicalizing([$tenantA->id, $tenantB->id], $tenants->pluck('id')->all());
    }

    public function test_get_default_tenant_prefers_configured_default(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);
        $user->tenants()->sync([$tenantA->id, $tenantB->id]);
        $user->forceFill(['default_tenant_id' => $tenantB->id])->save();

        $panel = app(Panel::class);

        $default = $user->getDefaultTenant($panel);

        $this->assertEquals($tenantB->id, $default->id);
    }

    public function test_can_access_tenant_checks_membership(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $other = Tenant::create(['name' => 'B', 'slug' => 'b']);
        $user->tenants()->sync([$tenant->id]);

        $this->assertTrue($user->canAccessTenant($tenant));
        $this->assertFalse($user->canAccessTenant($other));
    }
}
