<?php

namespace Tests\Feature\Api;

use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tile;
use App\Models\TimePeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TileMetricsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_filters_inactive_metric_definitions(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'slug' => ['de' => 'test-tile', 'en' => 'test-tile'],
        ]);

        $timePeriod = TimePeriod::create([
            'tile_id' => $tile->id,
            'period_key' => '2020',
            'granularity' => 'year',
            'label' => '2020',
        ]);

        $activeDefinition = MetricDefinition::create([
            'tile_id' => $tile->id,
            'metric_key' => 'active_metric',
            'label' => ['de' => 'Aktiv', 'en' => 'Active'],
            'unit' => ['de' => 't', 'en' => 't'],
            'indicator_type' => 'small',
            'is_active' => true,
        ]);

        $inactiveDefinition = MetricDefinition::create([
            'tile_id' => $tile->id,
            'metric_key' => 'inactive_metric',
            'label' => ['de' => 'Inaktiv', 'en' => 'Inactive'],
            'unit' => ['de' => 't', 'en' => 't'],
            'indicator_type' => 'small',
            'is_active' => false,
        ]);

        MetricValue::create([
            'metric_definition_id' => $activeDefinition->id,
            'time_period_id' => $timePeriod->id,
            'value' => 1,
            'is_active' => true,
        ]);

        MetricValue::create([
            'metric_definition_id' => $inactiveDefinition->id,
            'time_period_id' => $timePeriod->id,
            'value' => 2,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/tiles/{$tile->id}");

        $response->assertStatus(200);

        $definitions = $response->json('data.metric_definitions');
        $this->assertCount(1, $definitions);
        $this->assertSame('active_metric', $definitions[0]['metric_key']);
        $this->assertArrayNotHasKey('is_active', $definitions[0]);
    }

    public function test_api_filters_inactive_metric_values(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'slug' => ['de' => 'test-tile', 'en' => 'test-tile'],
        ]);

        $activeDefinition = MetricDefinition::create([
            'tile_id' => $tile->id,
            'metric_key' => 'active_metric',
            'label' => ['de' => 'Aktiv', 'en' => 'Active'],
            'unit' => ['de' => 't', 'en' => 't'],
            'indicator_type' => 'small',
            'is_active' => true,
        ]);

        $tp2020 = TimePeriod::create([
            'tile_id' => $tile->id,
            'period_key' => '2020',
            'granularity' => 'year',
            'label' => '2020',
        ]);

        $tp2021 = TimePeriod::create([
            'tile_id' => $tile->id,
            'period_key' => '2021',
            'granularity' => 'year',
            'label' => '2021',
        ]);

        MetricValue::create([
            'metric_definition_id' => $activeDefinition->id,
            'time_period_id' => $tp2020->id,
            'value' => 1,
            'is_active' => true,
        ]);

        MetricValue::create([
            'metric_definition_id' => $activeDefinition->id,
            'time_period_id' => $tp2021->id,
            'value' => 2,
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/tiles/{$tile->id}");

        $response->assertStatus(200);

        $values = $response->json('data.metric_definitions.0.values');
        $this->assertCount(1, $values);
        $this->assertSame('2020', $values[0]['period_key']);
        $this->assertArrayNotHasKey('is_active', $values[0]);
    }
}
