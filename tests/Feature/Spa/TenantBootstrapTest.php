<?php

namespace Tests\Feature\Spa;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_spa_shell_bootstraps_resolved_tenant_slug(): void
    {
        Tenant::factory()->create(['slug' => 'demo-city', 'domain' => 'demo-city.test']);

        $this->get('http://demo-city.test/')
            ->assertOk()
            ->assertSee('window.__TENANT__', false)
            ->assertSee('"slug":"demo-city"', false);
    }

    public function test_spa_shell_falls_back_to_default_tenant(): void
    {
        // TestCase::setUp() already seeds the "default" tenant for tenant-scoped tests.
        $this->get('http://unknown-host.test/')
            ->assertOk()
            ->assertSee('window.__TENANT__', false)
            ->assertSee('"slug":"default"', false);
    }
}
