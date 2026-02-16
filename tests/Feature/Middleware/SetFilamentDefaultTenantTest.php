<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\SetFilamentDefaultTenant;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SetFilamentDefaultTenantTest extends TestCase
{
    use RefreshDatabase;

    protected SetFilamentDefaultTenant $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new SetFilamentDefaultTenant;
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    public function test_default_tenant_set_for_user_with_default_tenant(): void
    {
        $tenant = Tenant::where('slug', 'default')->first();
        $user = User::factory()->create();
        // User is already attached to default tenant via User::booted()

        // Authenticate user in both Laravel and Filament contexts
        $this->actingAs($user);
        Filament::auth()->login($user);

        // Ensure no tenant is set
        Filament::setTenant(null);

        $request = Request::create('/admin', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertNotNull(Filament::getTenant());
        $this->assertEquals($tenant->id, Filament::getTenant()->id);
    }

    public function test_noop_when_tenant_already_set(): void
    {
        $existingTenant = Tenant::create([
            'name' => 'Already Set Tenant',
            'slug' => 'already-set',
        ]);

        $user = User::factory()->create();
        $user->tenants()->syncWithoutDetaching($existingTenant->id);

        $this->actingAs($user);
        Filament::auth()->login($user);

        // Set tenant before middleware runs
        Filament::setTenant($existingTenant);

        $request = Request::create('/admin', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        // Tenant should remain the one that was already set
        $this->assertEquals($existingTenant->id, Filament::getTenant()->id);
    }

    public function test_creates_default_tenant_in_test_context(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Filament::setTenant(null);

        $request = Request::create('/admin', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        // Should have resolved default tenant via user's getDefaultTenant()
        $this->assertNotNull(Filament::getTenant());
        $this->assertEquals('default', Filament::getTenant()->slug);
    }

    public function test_session_stores_tenant_key_when_available(): void
    {
        $tenant = Tenant::where('slug', 'default')->first();
        $user = User::factory()->create();

        $this->actingAs($user);
        Filament::auth()->login($user);
        Filament::setTenant(null);

        // Create a request with a session
        $request = Request::create('/admin', 'GET');
        $request->setLaravelSession(app('session.store'));

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals($tenant->id, $request->session()->get('filament.tenant'));
    }

    public function test_middleware_passes_request_to_next(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Filament::auth()->login($user);
        Filament::setTenant(null);

        $request = Request::create('/admin', 'GET');
        $called = false;

        $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return response('ok');
        });

        $this->assertTrue($called);
    }

    public function test_domain_matching_tenant_is_preferred_over_default(): void
    {
        $defaultTenant = Tenant::where('slug', 'default')->first();
        $demoCityTenant = Tenant::create([
            'name' => 'Demo City',
            'slug' => 'demo-city',
            'domain' => 'demo-city.example.com',
        ]);

        $user = User::factory()->create();
        $user->tenants()->syncWithoutDetaching($demoCityTenant->id);
        // User is already attached to default tenant via User::booted()

        $this->actingAs($user);
        Filament::auth()->login($user);
        Filament::setTenant(null);

        $request = Request::create('https://demo-city.example.com/admin', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertNotNull(Filament::getTenant());
        $this->assertEquals($demoCityTenant->id, Filament::getTenant()->id);
        $this->assertNotEquals($defaultTenant->id, Filament::getTenant()->id);
    }

    public function test_domain_matching_requires_user_access(): void
    {
        $demoCityTenant = Tenant::create([
            'name' => 'Demo City',
            'slug' => 'demo-city',
            'domain' => 'demo-city.example.com',
        ]);

        $user = User::factory()->create();
        // User is NOT attached to demo-city tenant

        $this->actingAs($user);
        Filament::auth()->login($user);
        Filament::setTenant(null);

        $request = Request::create('https://demo-city.example.com/admin', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        // Should fall back to default tenant (user's default), not demo-city
        $this->assertNotNull(Filament::getTenant());
        $this->assertNotEquals($demoCityTenant->id, Filament::getTenant()->id);
        $this->assertEquals('default', Filament::getTenant()->slug);
    }

    public function test_domain_with_no_match_falls_back_to_default(): void
    {
        $defaultTenant = Tenant::where('slug', 'default')->first();
        $user = User::factory()->create();

        $this->actingAs($user);
        Filament::auth()->login($user);
        Filament::setTenant(null);

        $request = Request::create('https://unknown.example.com/admin', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertNotNull(Filament::getTenant());
        $this->assertEquals($defaultTenant->id, Filament::getTenant()->id);
    }

    public function test_domain_matching_normalizes_www_and_case(): void
    {
        $demoCityTenant = Tenant::create([
            'name' => 'Demo City',
            'slug' => 'demo-city',
            'domain' => 'demo-city.example.com',
        ]);

        $user = User::factory()->create();
        $user->tenants()->syncWithoutDetaching($demoCityTenant->id);

        $this->actingAs($user);
        Filament::auth()->login($user);
        Filament::setTenant(null);

        // Host with WWW prefix and mixed case
        $request = Request::create('https://WWW.Demo-City.Example.COM/admin', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertNotNull(Filament::getTenant());
        $this->assertEquals($demoCityTenant->id, Filament::getTenant()->id);
    }

    public function test_query_param_tenant_overrides_default(): void
    {
        $demoCityTenant = Tenant::create([
            'name' => 'Demo City',
            'slug' => 'demo-city',
        ]);

        $user = User::factory()->create();
        $user->tenants()->syncWithoutDetaching($demoCityTenant->id);

        $this->actingAs($user);
        Filament::auth()->login($user);
        Filament::setTenant(null);

        $request = Request::create('/admin?tenant=demo-city', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertNotNull(Filament::getTenant());
        $this->assertEquals($demoCityTenant->id, Filament::getTenant()->id);
    }

    public function test_x_tenant_header_overrides_default(): void
    {
        $demoCityTenant = Tenant::create([
            'name' => 'Demo City',
            'slug' => 'demo-city',
        ]);

        $user = User::factory()->create();
        $user->tenants()->syncWithoutDetaching($demoCityTenant->id);

        $this->actingAs($user);
        Filament::auth()->login($user);
        Filament::setTenant(null);

        $request = Request::create('/admin', 'GET');
        $request->headers->set('X-Tenant', 'demo-city');

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertNotNull(Filament::getTenant());
        $this->assertEquals($demoCityTenant->id, Filament::getTenant()->id);
    }

    public function test_query_param_requires_user_access(): void
    {
        $demoCityTenant = Tenant::create([
            'name' => 'Demo City',
            'slug' => 'demo-city',
        ]);

        $user = User::factory()->create();
        // User is NOT attached to demo-city tenant

        $this->actingAs($user);
        Filament::auth()->login($user);
        Filament::setTenant(null);

        $request = Request::create('/admin?tenant=demo-city', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        // Should fall back to default tenant, not demo-city
        $this->assertNotNull(Filament::getTenant());
        $this->assertNotEquals($demoCityTenant->id, Filament::getTenant()->id);
        $this->assertEquals('default', Filament::getTenant()->slug);
    }
}
