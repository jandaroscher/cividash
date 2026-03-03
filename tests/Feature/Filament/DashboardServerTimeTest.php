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
            ['name' => 'Default Dashboard']
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

    public function test_server_time_shows_utc_prefix(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-19 10:15:00', 'UTC'));

        $settings = app(DashboardSettings::class);
        $settings->show_server_time = true;
        $settings->save();

        $response = $this->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Serverzeit', false);
        $response->assertSee('UTC 2026-01-19 10:15:00', false);
    }

    public function test_local_time_shows_timezone_prefix(): void
    {
        config(['app.timezone' => 'Europe/Berlin']);
        date_default_timezone_set('Europe/Berlin');

        Carbon::setTestNow(Carbon::parse('2026-01-19 11:15:00', 'Europe/Berlin'));

        $settings = app(DashboardSettings::class);
        $settings->show_server_time = true;
        $settings->save();

        $response = $this->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Lokale Zeit', false);
        $response->assertSee('Europe/Berlin 2026-01-19 11:15:00', false);
    }
}
