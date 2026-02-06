<?php

namespace Tests\Feature\Filament;

use App\Models\Tenant;
use App\Models\User;
use App\Settings\DashboardSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardContactTest extends TestCase
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

    public function test_dashboard_renders_contact_block_from_settings(): void
    {
        $settings = app(DashboardSettings::class);
        $settings->contact_name = 'Open Source Team';
        $settings->contact_email = 'opensource@example.test';
        $settings->contact_url = 'https://example.test/kontakt';
        $settings->save();

        $response = $this->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Kontakt', false);
        $response->assertSee('Open Source Team', false);
        $response->assertSee('opensource@example.test', false);
        $response->assertSee('https://example.test/kontakt', false);
    }

    public function test_dashboard_skips_contact_links_with_unsafe_values(): void
    {
        $settings = app(DashboardSettings::class);
        $settings->contact_name = 'Open Source Team';
        $settings->contact_email = 'not-an-email';
        $settings->contact_url = 'javascript:alert(1)';
        $settings->save();

        $response = $this->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Open Source Team', false);
        $response->assertDontSee('not-an-email', false);
        $response->assertDontSee('javascript:alert(1)', false);
        $response->assertDontSee('mailto:not-an-email', false);
    }
}
