<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportFilteredTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tile $mobility;

    private Tile $energy;

    private MetricDefinition $co2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::where('slug', 'default')->firstOrFail();

        $group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'topics',
            'is_active' => true,
        ]);

        $cMobility = Category::factory()->forGroup($group)->create(['key' => 'mobility', 'is_active' => true]);
        $cEnergy = Category::factory()->forGroup($group)->create(['key' => 'energy', 'is_active' => true]);

        $this->mobility = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Mobilität', 'en' => 'Mobility'],
            'slug' => ['de' => 'mobilitaet', 'en' => 'mobility'],
            'position' => 1,
        ]);
        $this->mobility->categories()->attach($cMobility);

        $this->energy = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
            'position' => 2,
        ]);
        $this->energy->categories()->attach($cEnergy);

        $this->co2 = MetricDefinition::factory()->forTile($this->mobility)->create(['metric_key' => 'co2']);

        foreach ([2018, 2020, 2022, 2024] as $year) {
            $ty = TimePeriod::factory()->forTile($this->mobility)->year($year)->create();
            MetricValue::factory()->forDefinition($this->co2)->forTimePeriod($ty)->create(['value' => $year]);
        }
    }

    public function test_category_filter_limits_result_set(): void
    {
        $response = $this->getJson('/api/exports/tiles?format=json&categories[]=energy');
        $body = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $titles = array_unique(array_column($body['data'], 'tile.title'));
        $this->assertSame(['Energie'], array_values($titles));
    }

    public function test_tile_id_filter_limits_result_set(): void
    {
        $response = $this->getJson('/api/exports/tiles?format=json&tiles[]='.$this->energy->id);
        $body = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $titles = array_unique(array_column($body['data'], 'tile.title'));
        $this->assertSame(['Energie'], array_values($titles));
    }

    public function test_year_range_filter_drops_values_outside_range(): void
    {
        $response = $this->getJson('/api/exports/tiles?format=json&tiles[]='.$this->mobility->id.'&year_from=2020&year_to=2022');
        $body = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $years = array_filter(array_column($body['data'], 'value.year'));
        sort($years);
        $this->assertSame([2020, 2022], array_values($years));
    }

    public function test_year_to_lower_than_year_from_returns_422(): void
    {
        $this->getJson('/api/exports/tiles?year_from=2024&year_to=2020')
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['year_to']]);
    }
}
