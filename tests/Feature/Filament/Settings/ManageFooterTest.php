<?php

namespace Tests\Feature\Filament\Settings;

use App\Filament\Pages\ManageFooter;
use App\Models\FooterNavigation;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageFooterTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->user = User::factory()->create();
        $this->user->tenants()->attach($this->tenant->id);
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
        Livewire::test(ManageFooter::class)
            ->assertSuccessful();
    }

    public function test_footer_record_is_created_on_mount(): void
    {
        Livewire::test(ManageFooter::class)
            ->assertSuccessful();

        $this->assertDatabaseHas('footer_navigations', [
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_layout_type_saves(): void
    {
        Livewire::test(ManageFooter::class)
            ->fillForm([
                'layout_type' => 'multi-column',
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

    public function test_layout_type_is_required(): void
    {
        Livewire::test(ManageFooter::class)
            ->fillForm([
                'layout_type' => '',
            ])
            ->call('save')
            ->assertHasFormErrors(['layout_type']);
    }

    public function test_social_links_enabled_toggle_saves(): void
    {
        Livewire::test(ManageFooter::class)
            ->fillForm([
                'social_links_enabled' => false,
                'layout_type' => 'single-row',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $footer = FooterNavigation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertFalse($footer->social_links_enabled);
    }

    public function test_copyright_text_saves(): void
    {
        Livewire::test(ManageFooter::class)
            ->fillForm([
                'copyright_text' => '(c) 2025 Test Company',
                'layout_type' => 'single-row',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $footer = FooterNavigation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->first();

        // copyright_text is translatable and stored as locale-keyed array
        // The mutateFormDataBeforeSave converts it to [locale => value] structure
        // The active locale in tests depends on the app default locale
        $rawCopyright = $footer->getRawOriginal('copyright_text');
        $decoded = is_string($rawCopyright) ? json_decode($rawCopyright, true) : $rawCopyright;

        $this->assertIsArray($decoded);
        // The value should be stored under some locale key
        $storedValues = array_filter($decoded, fn ($v) => $v !== '' && $v !== null);
        $this->assertNotEmpty($storedValues, 'Copyright text should be stored under at least one locale');
        $this->assertContains('(c) 2025 Test Company', $storedValues);
    }

    public function test_save_updates_existing_footer_record(): void
    {
        // First save to create
        Livewire::test(ManageFooter::class)
            ->fillForm(['layout_type' => 'single-row'])
            ->call('save');

        $countBefore = FooterNavigation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->count();

        // Second save should update, not create
        Livewire::test(ManageFooter::class)
            ->fillForm(['layout_type' => 'grid', 'columns' => 3])
            ->call('save');

        $countAfter = FooterNavigation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->count();

        $this->assertEquals($countBefore, $countAfter);
    }

    public function test_form_loads_existing_data(): void
    {
        FooterNavigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'footer_navigation_items' => ['de' => [], 'en' => []],
            'social_links' => ['de' => [], 'en' => []],
            'layout_type' => 'grid',
            'columns' => 4,
            'social_links_enabled' => false,
            'copyright_text' => ['de' => 'Test Copyright', 'en' => 'Test Copyright EN'],
        ]);

        Livewire::test(ManageFooter::class)
            ->assertFormSet([
                'layout_type' => 'grid',
                'columns' => 4,
                'social_links_enabled' => false,
            ]);
    }
}
