<?php

namespace Tests\Feature\Filament\Resources;

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
}
