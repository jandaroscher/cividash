<?php

namespace Tests\Feature\Filament\Settings;

use App\Filament\Pages\ManageNavigation;
use App\Models\Navigation;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageNavigationTest extends TestCase
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
        Livewire::test(ManageNavigation::class)
            ->assertSuccessful();
    }

    public function test_navigation_record_is_created_on_mount(): void
    {
        Livewire::test(ManageNavigation::class)
            ->assertSuccessful();

        $this->assertDatabaseHas('navigations', [
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_show_language_switcher_toggle_saves(): void
    {
        Livewire::test(ManageNavigation::class)
            ->fillForm([
                'show_language_switcher' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $navigation = Navigation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertFalse($navigation->show_language_switcher);
    }

    public function test_dropdown_enabled_toggle_saves(): void
    {
        Livewire::test(ManageNavigation::class)
            ->fillForm([
                'dropdown_enabled' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $navigation = Navigation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertTrue($navigation->dropdown_enabled);
    }

    public function test_save_persists_navigation_data(): void
    {
        $component = Livewire::test(ManageNavigation::class);

        $component->fillForm([
            'show_language_switcher' => true,
            'dropdown_enabled' => false,
        ]);

        $component->call('save')
            ->assertHasNoFormErrors();

        $navigation = Navigation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertNotNull($navigation);
        $this->assertTrue($navigation->show_language_switcher);
        $this->assertFalse($navigation->dropdown_enabled);
    }

    public function test_save_updates_existing_navigation_record(): void
    {
        // First save
        $component = Livewire::test(ManageNavigation::class);
        $component->set('data.show_language_switcher', true);
        $component->call('save');

        // Count records
        $countBefore = Navigation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->count();

        // Second save (should update, not create new)
        $component2 = Livewire::test(ManageNavigation::class);
        $component2->set('data.show_language_switcher', false);
        $component2->call('save');

        $countAfter = Navigation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->count();

        $this->assertEquals($countBefore, $countAfter);
    }

    public function test_form_loads_existing_data(): void
    {
        // Create a navigation with data
        Navigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'navigation_items' => ['de' => [], 'en' => []],
            'show_language_switcher' => false,
            'dropdown_enabled' => true,
        ]);

        Livewire::test(ManageNavigation::class)
            ->assertFormSet([
                'show_language_switcher' => false,
                'dropdown_enabled' => true,
            ]);
    }
}
