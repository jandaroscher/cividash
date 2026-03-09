<?php

namespace Tests\Feature\Filament\Settings;

use App\Filament\Pages\ManageSiteSettings;
use App\Models\FooterNavigation;
use App\Models\Navigation;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageSiteSettingsTest extends TestCase
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
        Livewire::test(ManageSiteSettings::class)
            ->assertSuccessful();
    }

    public function test_navigation_record_is_created_on_mount(): void
    {
        Livewire::test(ManageSiteSettings::class)
            ->assertSuccessful();

        $this->assertDatabaseHas('navigations', [
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_footer_record_is_created_on_mount(): void
    {
        Livewire::test(ManageSiteSettings::class)
            ->assertSuccessful();

        $this->assertDatabaseHas('footer_navigations', [
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_site_name_saves(): void
    {
        Livewire::test(ManageSiteSettings::class)
            ->fillForm([
                'site_name' => 'My Sustainability Dashboard',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        app()->forgetInstance(GeneralSettings::class);
        $settings = app(GeneralSettings::class);
        $this->assertEquals('My Sustainability Dashboard', $settings->site_name);
    }

    public function test_site_name_is_required(): void
    {
        Livewire::test(ManageSiteSettings::class)
            ->fillForm([
                'site_name' => '',
            ])
            ->call('save')
            ->assertHasFormErrors(['site_name']);
    }

    public function test_english_translation_active_saves(): void
    {
        Livewire::test(ManageSiteSettings::class)
            ->fillForm([
                'english_translation_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        app()->forgetInstance(GeneralSettings::class);
        $settings = app(GeneralSettings::class);
        $this->assertFalse($settings->english_translation_active);
    }

    public function test_no_dropdown_enabled_toggle_in_form(): void
    {
        $component = Livewire::test(ManageSiteSettings::class);

        // Children should always be visible (no dropdown_enabled toggle)
        // The form should not contain dropdown_enabled or show_language_switcher fields
        $component->assertFormFieldDoesNotExist('dropdown_enabled');
        $component->assertFormFieldDoesNotExist('show_language_switcher');
    }

    public function test_footer_column_count_saves_and_derives_layout_type(): void
    {
        Livewire::test(ManageSiteSettings::class)
            ->fillForm([
                'columns' => 4,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $footer = FooterNavigation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertEquals('multi-column', $footer->layout_type);
        $this->assertEquals(4, $footer->columns);
    }

    public function test_footer_single_column_derives_multi_column_layout(): void
    {
        Livewire::test(ManageSiteSettings::class)
            ->fillForm([
                'columns' => 1,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $footer = FooterNavigation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertEquals('multi-column', $footer->layout_type);
        $this->assertEquals(1, $footer->columns);
    }

    public function test_footer_social_links_enabled_saves(): void
    {
        Livewire::test(ManageSiteSettings::class)
            ->fillForm([
                'social_links_enabled' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $footer = FooterNavigation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertFalse($footer->social_links_enabled);
    }

    public function test_footer_copyright_text_saves(): void
    {
        Livewire::test(ManageSiteSettings::class)
            ->fillForm([
                'copyright_text' => '(c) 2025 Test Company',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $footer = FooterNavigation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $rawCopyright = $footer->getRawOriginal('copyright_text');
        $decoded = is_string($rawCopyright) ? json_decode($rawCopyright, true) : $rawCopyright;

        $this->assertIsArray($decoded);
        $storedValues = array_filter($decoded, fn ($v) => $v !== '' && $v !== null);
        $this->assertNotEmpty($storedValues);
        $this->assertContains('(c) 2025 Test Company', $storedValues);
    }

    public function test_form_loads_existing_data_from_all_sources(): void
    {
        $settings = app(GeneralSettings::class);
        $settings->site_name = 'Existing Site';
        $settings->english_translation_active = false;
        $settings->save();
        app()->forgetInstance(GeneralSettings::class);

        Navigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'navigation_items' => ['de' => [], 'en' => []],
            'dropdown_enabled' => true,
        ]);

        FooterNavigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'footer_navigation_items' => ['de' => [], 'en' => []],
            'social_links' => ['de' => [], 'en' => []],
            'layout_type' => 'grid',
            'columns' => 4,
            'social_links_enabled' => false,
            'copyright_text' => ['de' => 'Test Copyright', 'en' => 'Test Copyright EN'],
        ]);

        Livewire::test(ManageSiteSettings::class)
            ->assertFormSet([
                'site_name' => 'Existing Site',
                'english_translation_active' => false,
                'columns' => 4,
                'social_links_enabled' => false,
            ]);
    }

    public function test_only_admins_can_access(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $this->actingAs($nonAdmin);

        $this->assertFalse(ManageSiteSettings::canAccess());
    }

    public function test_admins_can_access(): void
    {
        $this->assertTrue(ManageSiteSettings::canAccess());
    }

    public function test_dropdown_enabled_always_saved_as_true(): void
    {
        Livewire::test(ManageSiteSettings::class)
            ->call('save')
            ->assertHasNoFormErrors();

        $navigation = Navigation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertTrue($navigation->dropdown_enabled);
    }
}
