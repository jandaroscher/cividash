<?php

namespace Tests\Feature\Filament;

use App\Models\Tenant;
use App\Models\User;
use App\Settings\DashboardSettings;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServerTimeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('de');

        $this->tenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default Tenant']
        );

        $this->user = User::factory()->create();
        $this->user->tenants()->sync([$this->tenant->id]);

        $this->actingAs($this->user);
        Filament::auth()->login($this->user);
        Filament::setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_renders_server_time_when_enabled(): void
    {
        $testNow = Carbon::parse('2026-01-19 10:15:00');
        Carbon::setTestNow($testNow);

        $settings = app(DashboardSettings::class);
        $settings->show_server_time = true;
        $settings->save();

        $response = $this->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Server', false);
        $response->assertSee('Serverzeit', false);
        $response->assertSee('2026-01-19 10:15:00', false);
    }
}
