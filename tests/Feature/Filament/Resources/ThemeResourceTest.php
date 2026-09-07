<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\ThemeResource;
use App\Filament\Resources\ThemeResource\Pages\ListThemes;
use App\Models\Tenant;
use App\Models\Theme;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ThemeResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->admin->tenants()->attach($this->tenant->id);

        $this->actingAs($this->admin);
        Filament::setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    public function test_list_page_renders(): void
    {
        Livewire::test(ListThemes::class)->assertSuccessful();
    }

    public function test_list_page_shows_all_themes_regardless_of_current_tenant(): void
    {
        // Theme is a global entity: it must list every theme, not just ones
        // linked to the currently active tenant (there is no such link).
        $theme = Theme::factory()->create();

        Livewire::test(ListThemes::class)
            ->assertCanSeeTableRecords([$theme]);
    }

    public function test_non_admin_cannot_access_theme_resource(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $nonAdmin->tenants()->attach($this->tenant->id);
        $this->actingAs($nonAdmin);

        $this->assertFalse(ThemeResource::canAccess());
    }
}
