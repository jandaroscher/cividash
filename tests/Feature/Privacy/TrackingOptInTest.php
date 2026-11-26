<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Privacy;

use App\Models\Tenant;
use App\Settings\TrackingSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CiviDash ships without tracking: the SPA shell only carries a tracking
 * configuration for a tenant whose admin entered a Matomo instance.
 */
class TrackingOptInTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_carry_no_tracking_by_default(): void
    {
        Tenant::factory()->create(['slug' => 'demo-city', 'domain' => 'demo-city.test']);

        $this->get('http://demo-city.test/')
            ->assertOk()
            ->assertDontSee('__TRACKING__', false)
            ->assertDontSee('matomo', false);
    }

    public function test_only_the_configured_tenant_gets_its_matomo_configuration(): void
    {
        $tracked = Tenant::factory()->create(['slug' => 'demo-city', 'domain' => 'demo-city.test']);
        Tenant::factory()->create(['slug' => 'other-city', 'domain' => 'other-city.test']);

        Filament::setTenant($tracked, isQuiet: true);
        $settings = app(TrackingSettings::class);
        $settings->matomo_url = 'https://matomo.example.org/';
        $settings->matomo_site_id = '7';
        $settings->save();
        Filament::setTenant(null, isQuiet: true);
        app()->forgetInstance(TrackingSettings::class);

        $this->get('http://demo-city.test/')
            ->assertOk()
            ->assertSee('window.__TRACKING__', false)
            ->assertSee('"provider":"matomo"', false)
            ->assertSee('"site_id":"7"', false);

        app()->forgetInstance(TrackingSettings::class);

        $this->get('http://other-city.test/')
            ->assertOk()
            ->assertDontSee('__TRACKING__', false);
    }
}
