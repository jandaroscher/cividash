<?php

namespace Tests\Feature\Filament;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Models\User;
use App\Settings\DashboardSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
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

    public function test_dashboard_renders_stats_and_made_with_text(): void
    {
        Tile::create([
            'title' => ['de' => 'Tile A', 'en' => 'Tile A'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);

        $tileB = Tile::create([
            'title' => ['de' => 'Tile B', 'en' => 'Tile B'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);

        TimePeriod::create([
            'tile_id' => $tileB->id,
            'period_key' => '2023',
            'granularity' => 'year',
            'label' => '2023',
        ]);
        TimePeriod::create([
            'tile_id' => $tileB->id,
            'period_key' => '2024',
            'granularity' => 'year',
            'label' => '2024',
        ]);
        TimePeriod::create([
            'tile_id' => $tileB->id,
            'period_key' => '2025',
            'granularity' => 'year',
            'label' => '2025',
        ]);

        $group = CategoryGroup::factory()->forTenant($this->tenant)->create();
        Category::factory()->forGroup($group)->count(3)->create();

        $settings = app(DashboardSettings::class);
        $settings->made_with_text = 'Made with ❤️ in Demo City';
        $settings->save();

        $response = $this->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Statistiken', false);
        $response->assertSee('2 aktive Kacheln', false);
        $response->assertSee('3 Zeitraumdaten', false);
        $response->assertSee('3 Kategorien', false);
        $response->assertSee('Kontakt', false);
        $response->assertSee('Made with ❤️ in Demo City', false);
    }
}
