<?php

namespace Tests\Feature\Filament\Settings;

use App\Filament\Pages\ManageGeneral;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageGeneralTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->user = User::factory()->admin()->create();
        $this->actingAs($this->user);
        Filament::setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    public function test_page_renders(): void
    {
        Livewire::test(ManageGeneral::class)
            ->assertSuccessful();
    }

    public function test_site_name_saves(): void
    {
        Livewire::test(ManageGeneral::class)
            ->fillForm([
                'site_name' => 'My Sustainability Dashboard',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(GeneralSettings::class);
        $this->assertEquals('My Sustainability Dashboard', $settings->site_name);
    }

    public function test_site_name_is_required(): void
    {
        Livewire::test(ManageGeneral::class)
            ->fillForm([
                'site_name' => '',
            ])
            ->call('save')
            ->assertHasFormErrors(['site_name']);
    }

    public function test_form_loads_existing_settings(): void
    {
        $settings = app(GeneralSettings::class);
        $settings->site_name = 'Existing Name';
        $settings->save();

        Livewire::test(ManageGeneral::class)
            ->assertFormSet([
                'site_name' => 'Existing Name',
            ]);
    }

    public function test_form_loads_tenant_specific_settings_not_global(): void
    {
        // Write a tenant-specific site_name
        $settings = app(GeneralSettings::class);
        $settings->site_name = 'Tenant-Specific Name';
        $settings->save();
        app()->forgetInstance(GeneralSettings::class);

        // Verify form loads the tenant-specific value
        Livewire::test(ManageGeneral::class)
            ->assertFormSet([
                'site_name' => 'Tenant-Specific Name',
            ]);
    }

    public function test_save_updates_existing_settings(): void
    {
        // First save
        Livewire::test(ManageGeneral::class)
            ->fillForm(['site_name' => 'First Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('First Name', app(GeneralSettings::class)->site_name);

        // Second save should update
        Livewire::test(ManageGeneral::class)
            ->fillForm(['site_name' => 'Updated Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        // Re-resolve settings to get fresh values
        app()->forgetInstance(GeneralSettings::class);
        $this->assertEquals('Updated Name', app(GeneralSettings::class)->site_name);
    }
}
