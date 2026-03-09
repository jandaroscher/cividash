<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilterApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::where('slug', 'default')->first();
    }

    public function test_filters_endpoint_returns_dynamic_groups(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'fields',
            'title' => ['de' => 'Handlungsfelder', 'en' => 'Action Fields'],
            'position' => 0,
        ]);

        Category::factory()->forGroup($group)->create([
            'slug' => ['de' => 'Digitalisierung', 'en' => 'Digitalization'],
            'position' => 0,
        ]);

        $response = $this->getJson('/api/filters?locale=de');

        $response->assertStatus(200)
            ->assertJsonPath('data.labels.header', 'Filter')
            ->assertJsonPath('data.groups.0.key', 'fields')
            ->assertJsonPath('data.groups.0.items.0.title', 'Digitalisierung');
    }

    public function test_multiple_filter_groups_returned_simultaneously(): void
    {
        $group1 = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'fields',
            'title' => ['de' => 'Handlungsfelder', 'en' => 'Action Fields'],
            'position' => 0,
        ]);

        $group2 = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'dimensions',
            'title' => ['de' => 'Dimensionen', 'en' => 'Dimensions'],
            'position' => 1,
        ]);

        Category::factory()->forGroup($group1)->create([
            'slug' => ['de' => 'Energie', 'en' => 'Energy'],
            'position' => 0,
        ]);

        Category::factory()->forGroup($group2)->create([
            'slug' => ['de' => 'Sozial', 'en' => 'Social'],
            'position' => 0,
        ]);

        $response = $this->getJson('/api/filters?locale=de');

        $response->assertStatus(200);

        $groups = $response->json('data.groups');
        $this->assertCount(2, $groups);
        $this->assertEquals('fields', $groups[0]['key']);
        $this->assertEquals('dimensions', $groups[1]['key']);
    }

    public function test_locale_de_returns_german_filter_labels_and_group_titles(): void
    {
        CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'fields',
            'title' => ['de' => 'Handlungsfelder', 'en' => 'Action Fields'],
            'position' => 0,
        ]);

        $response = $this->getJson('/api/filters?locale=de');

        $response->assertStatus(200)
            ->assertJsonPath('data.labels.header', 'Filter')
            ->assertJsonPath('data.groups.0.title', 'Handlungsfelder');
    }

    public function test_locale_en_returns_english_group_titles(): void
    {
        CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'fields',
            'title' => ['de' => 'Handlungsfelder', 'en' => 'Action Fields'],
            'position' => 0,
        ]);

        $response = $this->getJson('/api/filters?locale=en');

        $response->assertStatus(200)
            ->assertJsonPath('data.labels.header', 'Filter')
            ->assertJsonPath('data.groups.0.title', 'Action Fields');
    }

    public function test_locale_affects_category_item_labels(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'fields',
            'title' => ['de' => 'Handlungsfelder', 'en' => 'Action Fields'],
            'position' => 0,
        ]);

        Category::factory()->forGroup($group)->create([
            'slug' => ['de' => 'Energie', 'en' => 'Energy'],
            'position' => 0,
        ]);

        $responseDe = $this->getJson('/api/filters?locale=de');
        $responseDe->assertStatus(200)
            ->assertJsonPath('data.groups.0.items.0.title', 'Energie');

        $responseEn = $this->getJson('/api/filters?locale=en');
        $responseEn->assertStatus(200)
            ->assertJsonPath('data.groups.0.items.0.title', 'Energy');
    }

    public function test_empty_filterable_groups_handled_correctly(): void
    {
        CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'empty-group',
            'title' => ['de' => 'Leere Gruppe', 'en' => 'Empty Group'],
            'position' => 0,
        ]);

        $response = $this->getJson('/api/filters?locale=de');

        $response->assertStatus(200);

        $groups = $response->json('data.groups');
        $this->assertCount(1, $groups);
        $this->assertEquals('empty-group', $groups[0]['key']);
        $this->assertEmpty($groups[0]['items']);
    }

    public function test_all_active_groups_appear_as_filters(): void
    {
        CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'group-one',
            'title' => ['de' => 'Gruppe Eins', 'en' => 'Group One'],
            'position' => 0,
        ]);

        CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'group-two',
            'title' => ['de' => 'Gruppe Zwei', 'en' => 'Group Two'],
            'position' => 1,
        ]);

        $response = $this->getJson('/api/filters?locale=de');

        $response->assertStatus(200);

        $groups = $response->json('data.groups');
        $this->assertCount(2, $groups);
        $this->assertEquals('group-one', $groups[0]['key']);
        $this->assertEquals('group-two', $groups[1]['key']);
    }

    public function test_inactive_groups_are_excluded_from_filters(): void
    {
        CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'active-group',
            'title' => ['de' => 'Aktiv', 'en' => 'Active'],
            'is_active' => true,
            'position' => 0,
        ]);

        CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'inactive-group',
            'title' => ['de' => 'Inaktiv', 'en' => 'Inactive'],
            'is_active' => false,
            'position' => 1,
        ]);

        $response = $this->getJson('/api/filters?locale=de');

        $response->assertStatus(200);

        $groups = $response->json('data.groups');
        $this->assertCount(1, $groups);
        $this->assertEquals('active-group', $groups[0]['key']);
    }

    public function test_inactive_categories_excluded_from_filter_groups(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'fields',
            'title' => ['de' => 'Handlungsfelder', 'en' => 'Action Fields'],
            'position' => 0,
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

        $response = $this->getJson('/api/filters?locale=de');

        $response->assertStatus(200);

        $items = $response->json('data.groups.0.items');
        $this->assertCount(1, $items);
        $this->assertEquals('Aktiv', $items[0]['title']);
    }

    public function test_filter_groups_ordered_by_position(): void
    {
        CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'second',
            'title' => ['de' => 'Zweite', 'en' => 'Second'],
            'position' => 2,
        ]);

        CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'first',
            'title' => ['de' => 'Erste', 'en' => 'First'],
            'position' => 1,
        ]);

        $response = $this->getJson('/api/filters?locale=de');

        $response->assertStatus(200);

        $groups = $response->json('data.groups');
        $this->assertEquals('first', $groups[0]['key']);
        $this->assertEquals('second', $groups[1]['key']);
    }

    public function test_default_locale_falls_back_to_de(): void
    {
        CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'fields',
            'title' => ['de' => 'Handlungsfelder', 'en' => 'Action Fields'],
            'position' => 0,
        ]);

        // No locale parameter - should default to 'de'
        $response = $this->getJson('/api/filters');

        $response->assertStatus(200)
            ->assertJsonPath('data.groups.0.title', 'Handlungsfelder');
    }
}
