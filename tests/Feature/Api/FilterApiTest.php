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

    public function test_filters_endpoint_returns_dynamic_groups(): void
    {
        $tenant = Tenant::create([
            'name' => 'Default Tenant',
            'slug' => 'default',
        ]);

        $group = CategoryGroup::create([
            'tenant_id' => $tenant->id,
            'key' => 'fields',
            'title' => ['de' => 'Handlungsfelder', 'en' => 'Action Fields'],
            'position' => 0,
            'is_filterable' => true,
            'selection_type' => 'multi',
        ]);

        Category::create([
            'tenant_id' => $tenant->id,
            'category_group_id' => $group->id,
            'slug' => ['de' => 'Digitalisierung', 'en' => 'Digitalization'],
            'position' => 0,
        ]);

        $response = $this->getJson('/api/filters?locale=de');

        $response->assertStatus(200)
            ->assertJsonPath('data.labels.header', 'Filter')
            ->assertJsonPath('data.groups.0.key', 'fields')
            ->assertJsonPath('data.groups.0.items.0.title', 'Digitalisierung');
    }
}
