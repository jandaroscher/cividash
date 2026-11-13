<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Filament;

use App\Models\Tenant;
use App\Models\User;
use App\Settings\DashboardSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFooterTest extends TestCase
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

    public function test_dashboard_does_not_show_stored_contact_details(): void
    {
        $settings = app(DashboardSettings::class);
        $settings->contact_name = 'Open Source Team';
        $settings->contact_email = 'opensource@example.test';
        $settings->contact_url = 'https://example.test/kontakt';
        $settings->made_with_text = 'Made with love in Demo City';
        $settings->save();

        $response = $this->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertDontSee('Open Source Team', false);
        $response->assertDontSee('opensource@example.test', false);
        $response->assertDontSee('https://example.test/kontakt', false);
        $response->assertDontSee('Made with love in Demo City', false);
    }

    public function test_dashboard_shows_developed_in_line_with_configured_place_in_german(): void
    {
        $settings = app(DashboardSettings::class);
        $settings->made_in = 'Demo City';
        $settings->save();

        $response = $this->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Mit ❤️ in Demo City entwickelt', false);
    }

    public function test_dashboard_shows_developed_in_line_with_configured_place_in_english(): void
    {
        $settings = app(DashboardSettings::class);
        $settings->made_in = 'Demo City';
        $settings->save();

        $response = $this->withSession(['locale' => 'en'])->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Developed with ❤️ in Demo City', false);
    }

    public function test_dashboard_hides_developed_in_line_without_place(): void
    {
        $response = $this->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertDontSee('entwickelt', false);
    }
}
