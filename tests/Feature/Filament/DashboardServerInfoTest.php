<?php

namespace Tests\Feature\Filament;

use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use Tests\TestCase;

class DashboardServerInfoTest extends TestCase
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

    public function test_dashboard_renders_server_metadata(): void
    {
        $driver = DB::connection()->getDriverName();
        $serverVersion = DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
        $dbInfo = trim($driver.' '.$serverVersion);

        $response = $this->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('PHP-Version', false);
        $response->assertSee(phpversion(), false);
        $response->assertSee('Laravel-Version', false);
        $response->assertSee(app()->version(), false);
        $response->assertSee('Datenbank', false);
        $response->assertSee($dbInfo, false);
        $response->assertSee('Umgebung', false);
        $response->assertSee(config('app.env'), false);
        $response->assertSee('Zeitzone', false);
        $response->assertSee(config('app.timezone'), false);
    }
}
