<?php

namespace Tests\Feature\Spa;

use App\Http\Controllers\SpaController;
use App\Models\Tenant;
use App\Services\MetaTagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TenantBootstrapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Renders the real app.blade.php shell; the built manifest isn't present
        // in every CI leg (e.g. the PostgreSQL job never runs `npm run build`).
        $this->withoutVite();
    }

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

    public function test_spa_shell_falls_back_when_no_tenant_resolved_at_all(): void
    {
        // Bypasses resolve.tenant entirely (no 'resolved_tenant' request attribute set),
        // e.g. the middleware chain didn't run or found nothing — forces the controller's
        // own ternary-else guard, not the middleware's default-tenant lookup.
        $request = Request::create('/');

        $response = $this->app->call([$this->app->make(SpaController::class), 'index'], [
            'request' => $request,
            'metaTagService' => $this->app->make(MetaTagService::class),
        ]);

        $html = $response->render();

        $this->assertStringContainsString('window.__TENANT__', $html);
        $this->assertStringContainsString('"slug":"default"', $html);
        $this->assertStringContainsString('"name":null', $html);
    }
}
