<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Filament\Settings;

use App\Filament\Pages\ManageTracking;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\TrackingSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Demo City', 'slug' => 'demo-city']);
        $this->actingAs(User::factory()->admin()->create());
        Filament::setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    public function test_page_renders_for_admin(): void
    {
        Livewire::test(ManageTracking::class)->assertSuccessful();
    }

    public function test_page_not_accessible_for_non_admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        Livewire::test(ManageTracking::class)->assertForbidden();
    }

    public function test_saves_matomo_settings_for_the_current_tenant_only(): void
    {
        Livewire::test(ManageTracking::class)
            ->fillForm(['matomo_url' => 'https://matomo.example.org', 'matomo_site_id' => '7'])
            ->call('save')
            ->assertHasNoFormErrors();

        app()->forgetInstance(TrackingSettings::class);
        $this->assertSame(
            ['provider' => 'matomo', 'url' => 'https://matomo.example.org/', 'site_id' => '7'],
            app(TrackingSettings::class)->frontendConfig(),
        );

        Filament::setTenant(Tenant::create(['name' => 'Other', 'slug' => 'other']), isQuiet: true);
        app()->forgetInstance(TrackingSettings::class);
        $this->assertNull(app(TrackingSettings::class)->frontendConfig());
    }

    public function test_rejects_a_matomo_url_without_https(): void
    {
        Livewire::test(ManageTracking::class)
            ->fillForm(['matomo_url' => 'http://matomo.example.org/', 'matomo_site_id' => '7'])
            ->call('save')
            ->assertHasFormErrors(['matomo_url']);
    }

    public function test_rejects_a_matomo_url_with_query_or_fragment(): void
    {
        foreach (['https://matomo.example.org/#', 'https://matomo.example.org/?x=1'] as $url) {
            Livewire::test(ManageTracking::class)
                ->fillForm(['matomo_url' => $url, 'matomo_site_id' => '7'])
                ->call('save')
                ->assertHasFormErrors(['matomo_url']);
        }
    }

    public function test_requires_both_url_and_numeric_site_id(): void
    {
        Livewire::test(ManageTracking::class)
            ->fillForm(['matomo_url' => 'https://matomo.example.org/', 'matomo_site_id' => ''])
            ->call('save')
            ->assertHasFormErrors(['matomo_site_id']);

        Livewire::test(ManageTracking::class)
            ->fillForm(['matomo_url' => '', 'matomo_site_id' => '7'])
            ->call('save')
            ->assertHasFormErrors(['matomo_url']);

        Livewire::test(ManageTracking::class)
            ->fillForm(['matomo_url' => 'https://matomo.example.org/', 'matomo_site_id' => '7);alert(1'])
            ->call('save')
            ->assertHasFormErrors(['matomo_site_id']);
    }

    public function test_clearing_both_fields_switches_tracking_off(): void
    {
        Livewire::test(ManageTracking::class)
            ->fillForm(['matomo_url' => 'https://matomo.example.org/', 'matomo_site_id' => '7'])
            ->call('save');

        Livewire::test(ManageTracking::class)
            ->fillForm(['matomo_url' => '', 'matomo_site_id' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        app()->forgetInstance(TrackingSettings::class);
        $this->assertNull(app(TrackingSettings::class)->frontendConfig());
    }
}
