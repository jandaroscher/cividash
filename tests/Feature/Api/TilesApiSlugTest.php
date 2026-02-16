<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Tenant;
use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TilesApiSlugTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::where('slug', 'default')->first();
    }

    public function test_show_by_numeric_id(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
            'is_public' => true,
            'position' => 0,
        ]);

        $this->getJson("/api/tiles/{$tile->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $tile->id)
            ->assertJsonPath('data.title', ['de' => 'Energie', 'en' => 'Energy']);
    }

    public function test_show_by_slug_with_locale(): void
    {
        Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
            'is_public' => true,
            'position' => 0,
        ]);

        $this->getJson('/api/tiles/energie?locale=de')
            ->assertOk()
            ->assertJsonPath('data.title', 'Energie')
            ->assertJsonPath('data.slug', 'energie');
    }

    public function test_show_slug_locale_fallback(): void
    {
        Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Mobilität', 'en' => 'Mobility'],
            'slug' => ['de' => 'mobilitaet', 'en' => 'mobility'],
            'is_public' => true,
            'position' => 0,
        ]);

        // Request with locale=en but use the German slug - should fall back to de lookup
        $this->getJson('/api/tiles/mobilitaet?locale=en')
            ->assertOk()
            ->assertJsonPath('data.title', 'Mobility');
    }

    public function test_show_nonexistent_slug_returns_404(): void
    {
        $this->getJson('/api/tiles/nonexistent')
            ->assertNotFound();
    }

    public function test_index_excludes_private_tiles(): void
    {
        Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Öffentlich', 'en' => 'Public'],
            'is_public' => true,
            'position' => 0,
        ]);

        Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Privat', 'en' => 'Private'],
            'is_public' => false,
            'position' => 1,
        ]);

        $response = $this->getJson('/api/tiles?locale=de');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Öffentlich');
    }

    public function test_show_excludes_private_tile_by_slug(): void
    {
        Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Geheim', 'en' => 'Secret'],
            'slug' => ['de' => 'geheim', 'en' => 'secret'],
            'is_public' => false,
            'position' => 0,
        ]);

        $this->getJson('/api/tiles/geheim?locale=de')
            ->assertNotFound();
    }

    public function test_index_excludes_inactive_categories(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'is_active' => true,
        ]);

        $activeCategory = Category::factory()->forTenant($this->tenant)->forGroup($group)->create([
            'is_active' => true,
            'slug' => ['de' => 'aktiv', 'en' => 'active'],
        ]);

        $inactiveCategory = Category::factory()->forTenant($this->tenant)->forGroup($group)->create([
            'is_active' => false,
            'slug' => ['de' => 'inaktiv', 'en' => 'inactive'],
        ]);

        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Testtile', 'en' => 'Test tile'],
            'is_public' => true,
            'position' => 0,
        ]);

        $tile->categories()->attach([$activeCategory->id, $inactiveCategory->id]);

        $response = $this->getJson('/api/tiles?locale=de');

        $response->assertOk();

        $categories = $response->json('data.0.categories');
        $categoryIds = collect($categories)->pluck('id')->all();

        $this->assertContains($activeCategory->id, $categoryIds);
        $this->assertNotContains($inactiveCategory->id, $categoryIds);
    }
}
