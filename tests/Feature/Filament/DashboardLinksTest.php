<?php

namespace Tests\Feature\Filament;

use App\Models\Tenant;
use App\Models\User;
use App\Settings\DashboardSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardLinksTest extends TestCase
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

    public function test_dashboard_renders_content_links_from_settings(): void
    {
        $settings = app(DashboardSettings::class);
        $settings->open_source_docs_url = 'https://example.test/open-source';
        $settings->user_manual_url = 'https://example.test/handbuch';
        $settings->save();

        $response = $this->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Open-Source-Dokumentation', false);
        $response->assertSee('https://example.test/open-source', false);
        $response->assertSee('Nutzerhandbuch', false);
        $response->assertSee('https://example.test/handbuch', false);
    }

    public function test_dashboard_skips_content_links_with_unsafe_schemes(): void
    {
        $settings = app(DashboardSettings::class);
        $settings->open_source_docs_url = 'javascript:alert(1)';
        $settings->user_manual_url = 'ftp://example.test/manual';
        $settings->save();

        $response = $this->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertDontSee('javascript:alert(1)', false);
        $response->assertDontSee('ftp://example.test/manual', false);
        $response->assertSee('Keine Links konfiguriert.', false);
    }
}
