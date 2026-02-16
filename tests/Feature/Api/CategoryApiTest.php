<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::where('slug', 'default')->firstOrFail();
    }

    public function test_valid_group_key_returns_categories(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'fields',
        ]);

        Category::factory()->forGroup($group)->create([
            'slug' => ['de' => 'Energie', 'en' => 'Energy'],
            'position' => 0,
        ]);

        Category::factory()->forGroup($group)->create([
            'slug' => ['de' => 'Mobilität', 'en' => 'Mobility'],
            'position' => 1,
        ]);

        $response = $this->getJson('/api/categories/fields');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_invalid_group_key_returns_empty_collection(): void
    {
        $response = $this->getJson('/api/categories/nonexistent');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_empty_group_returns_empty_collection(): void
    {
        CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'empty-group',
        ]);

        $response = $this->getJson('/api/categories/empty-group');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_locale_de_returns_german_translations(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'fields',
        ]);

        Category::factory()->forGroup($group)->create([
            'slug' => ['de' => 'Energie', 'en' => 'Energy'],
            'position' => 0,
        ]);

        $response = $this->getJson('/api/categories/fields?locale=de');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.title', 'Energie');
    }

    public function test_locale_en_returns_english_translations(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'fields',
        ]);

        Category::factory()->forGroup($group)->create([
            'slug' => ['de' => 'Energie', 'en' => 'Energy'],
            'position' => 0,
        ]);

        $response = $this->getJson('/api/categories/fields?locale=en');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.title', 'Energy');
    }

    public function test_inactive_group_returns_empty_collection(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'inactive-group',
            'is_active' => false,
        ]);

        Category::factory()->forGroup($group)->create([
            'slug' => ['de' => 'Energie', 'en' => 'Energy'],
        ]);

        $response = $this->getJson('/api/categories/inactive-group');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_inactive_categories_are_filtered_out(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'fields',
        ]);

        Category::factory()->forGroup($group)->create([
            'slug' => ['de' => 'Aktiv', 'en' => 'Active'],
            'is_active' => true,
            'position' => 0,
        ]);

        Category::factory()->forGroup($group)->create([
            'slug' => ['de' => 'Inaktiv', 'en' => 'Inactive'],
            'is_active' => false,
            'position' => 1,
        ]);

        $response = $this->getJson('/api/categories/fields?locale=de');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Aktiv');
    }

    public function test_categories_are_ordered_by_position(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'fields',
        ]);

        Category::factory()->forGroup($group)->create([
            'slug' => ['de' => 'Zweite', 'en' => 'Second'],
            'position' => 2,
        ]);

        Category::factory()->forGroup($group)->create([
            'slug' => ['de' => 'Erste', 'en' => 'First'],
            'position' => 1,
        ]);

        $response = $this->getJson('/api/categories/fields?locale=de');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.title', 'Erste')
            ->assertJsonPath('data.1.title', 'Zweite');
    }
}
