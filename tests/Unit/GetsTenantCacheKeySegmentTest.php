<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\User;
use App\Traits\GetsTenantCacheKeySegment;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class GetsTenantCacheKeySegmentTest extends TestCase
{
    use RefreshDatabase;

    protected object $traitInstance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->traitInstance = new class
        {
            use GetsTenantCacheKeySegment;

            public function callGetTenantCacheKeySegment(?int $tenantId = null): string
            {
                return $this->getTenantCacheKeySegment($tenantId);
            }
        };
    }

    public function test_returns_explicit_tenant_id_when_passed(): void
    {
        $result = $this->traitInstance->callGetTenantCacheKeySegment(42);

        $this->assertEquals('42', $result);
    }

    public function test_returns_filament_tenant_id_when_set(): void
    {
        $tenant = Tenant::where('slug', 'default')->first();
        $user = User::factory()->create();
        // User is already attached to default tenant via User::booted()
        $user->tenants()->syncWithoutDetaching($tenant->id);
        $this->actingAs($user);
        Filament::setTenant($tenant);

        $result = $this->traitInstance->callGetTenantCacheKeySegment();

        $this->assertEquals((string) $tenant->id, $result);

        Filament::setTenant(null);
    }

    public function test_returns_request_attribute_tenant_when_filament_unavailable(): void
    {
        $tenant = Tenant::where('slug', 'default')->first();

        // Set up request attribute
        $request = Request::create('/test');
        $request->attributes->set('resolved_tenant', $tenant);
        $this->app->instance('request', $request);

        $result = $this->traitInstance->callGetTenantCacheKeySegment();

        $this->assertEquals((string) $tenant->id, $result);
    }

    public function test_returns_public_when_no_tenant_available(): void
    {
        // Ensure Filament has no tenant and request has no resolved_tenant
        $request = Request::create('/test');
        $this->app->instance('request', $request);

        $result = $this->traitInstance->callGetTenantCacheKeySegment();

        $this->assertEquals('public', $result);
    }

    public function test_explicit_tenant_id_takes_priority_over_filament(): void
    {
        $tenant = Tenant::where('slug', 'default')->first();
        $user = User::factory()->create();
        // User is already attached to default tenant via User::booted()
        $user->tenants()->syncWithoutDetaching($tenant->id);
        $this->actingAs($user);
        Filament::setTenant($tenant);

        // Pass explicit ID that differs from the Filament tenant
        $result = $this->traitInstance->callGetTenantCacheKeySegment(999);

        $this->assertEquals('999', $result);

        Filament::setTenant(null);
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function test_returns_tenant_id_from_global_tenant_function(): void
    {
        // Define a global tenant() function that returns a fake tenant object
        // This must run in a separate process to avoid polluting the global namespace
        eval('function tenant() { return new class { public int $id = 77; public function getKey() { return 77; } }; }');

        // Create a fresh trait instance in this process
        $instance = new class
        {
            use GetsTenantCacheKeySegment;

            public function callGetTenantCacheKeySegment(?int $tenantId = null): string
            {
                return $this->getTenantCacheKeySegment($tenantId);
            }
        };

        $result = $instance->callGetTenantCacheKeySegment();

        $this->assertEquals('77', $result);
    }
}
