<?php

namespace Tests\Feature;

use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Services\DashboardJsonParser;
use App\Services\ParsedMetric;
use Database\Seeders\MetricSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MetricSeederTest extends TestCase
{
    use RefreshDatabase;

    protected ?MetricSeeder $seeder = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user and authenticate for Filament tenant context
        $user = \App\Models\User::factory()->create();
        $tenant = Tenant::where('slug', 'default')->first();
        if ($tenant) {
            $user->tenants()->sync([$tenant->id]);
            Filament::auth()->login($user);
            Filament::setTenant($tenant);
        }

        $this->seeder = new MetricSeeder;
        // Ensure ParsedMetric class is loaded by referencing DashboardJsonParser
        class_exists(DashboardJsonParser::class);
    }

    public function test_seeder_creates_time_periods_and_metrics(): void
    {
        // Create a tile
        $tile = new Tile;
        $tile->title = ['de' => 'Test Tile', 'en' => 'Test Tile EN'];
        $tile->description = ['de' => 'Test Description', 'en' => 'Test Description EN'];
        $tile->position = 1;
        $tile->save();

        // Create parsed metrics with years
        $parsedMetric = new ParsedMetric(
            id: 101,
            key: 'test_metric',
            title: 'Test Kennzahl',
            titleEn: 'Test Metric',
            unit: 'Stück',
            icon: '/uploads/icons/metric.png',
            years: [
                ['year' => 2020, 'value' => 100],
                ['year' => 2021, 'value' => 110],
                ['year' => 2022, 'value' => 120],
            ]
        );

        $tileIdMap = [$parsedMetric->id => $tile->id];
        $tileMetricMapping = [$parsedMetric->id => [$parsedMetric->id]];

        $this->seeder->run(
            Collection::make([$parsedMetric]),
            $tileIdMap,
            $tileMetricMapping
        );

        // Check TimePeriods were created
        $timePeriods = TimePeriod::where('tile_id', $tile->id)->get();
        $this->assertCount(3, $timePeriods);

        // Check MetricDefinition was created
        $metricDefinition = MetricDefinition::where('tile_id', $tile->id)
            ->where('metric_key', 'test_metric')
            ->first();
        $this->assertNotNull($metricDefinition);
        $this->assertEquals('Test Kennzahl', $metricDefinition->getTranslation('label', 'de'));
        $this->assertEquals('Test Metric', $metricDefinition->getTranslation('label', 'en'));
        $this->assertEquals('Stück', $metricDefinition->getTranslation('unit', 'de'));
        $this->assertEquals('seeds/metrics/metric.png', $metricDefinition->icon);

        // Check MetricValues were created
        $metricValues = MetricValue::whereIn('time_period_id', $timePeriods->pluck('id'))
            ->where('metric_definition_id', $metricDefinition->id)
            ->get();
        $this->assertCount(3, $metricValues);

        // Check first metric value
        $firstMetricValue = $metricValues->first();
        $this->assertEquals(100.0, $firstMetricValue->value);
    }

    public function test_seeder_is_idempotent(): void
    {
        // Create a tile
        $tile = new Tile;
        $tile->title = ['de' => 'Test Tile', 'en' => 'Test Tile EN'];
        $tile->description = ['de' => 'Test Description', 'en' => 'Test Description EN'];
        $tile->position = 1;
        $tile->save();

        // Create parsed metrics
        $parsedMetric = new ParsedMetric(
            id: 101,
            key: 'test_metric',
            title: 'Test Kennzahl',
            titleEn: 'Test Metric',
            unit: 'Stück',
            icon: '/uploads/icons/metric.png',
            years: [
                ['year' => 2020, 'value' => 100],
            ]
        );

        $tileIdMap = [$parsedMetric->id => $tile->id];
        $tileMetricMapping = [$parsedMetric->id => [$parsedMetric->id]];

        // Run seeder twice
        $this->seeder->run(
            Collection::make([$parsedMetric]),
            $tileIdMap,
            $tileMetricMapping
        );

        $firstRunTimePeriodCount = TimePeriod::where('tile_id', $tile->id)->count();
        $firstRunMetricDefinitionCount = MetricDefinition::where('tile_id', $tile->id)->count();
        $firstRunMetricValueCount = MetricValue::count();

        $this->seeder->run(
            Collection::make([$parsedMetric]),
            $tileIdMap,
            $tileMetricMapping
        );

        // Should not create duplicates
        $this->assertEquals($firstRunTimePeriodCount, TimePeriod::where('tile_id', $tile->id)->count());
        $this->assertEquals($firstRunMetricDefinitionCount, MetricDefinition::where('tile_id', $tile->id)->count());
        $this->assertEquals($firstRunMetricValueCount, MetricValue::count());
    }

    public function test_seeder_handles_multiple_metrics_per_tile(): void
    {
        // Create a tile
        $tile = new Tile;
        $tile->title = ['de' => 'Test Tile', 'en' => 'Test Tile EN'];
        $tile->description = ['de' => 'Test Description', 'en' => 'Test Description EN'];
        $tile->position = 1;
        $tile->save();

        // Create multiple parsed metrics
        $parsedMetric1 = new ParsedMetric(
            id: 101,
            key: 'metric1',
            title: 'Metric 1',
            titleEn: 'Metric 1 EN',
            unit: 'Stück',
            icon: null,
            years: [
                ['year' => 2020, 'value' => 100],
            ]
        );

        $parsedMetric2 = new ParsedMetric(
            id: 102,
            key: 'metric2',
            title: 'Metric 2',
            titleEn: 'Metric 2 EN',
            unit: 'kg',
            icon: null,
            years: [
                ['year' => 2020, 'value' => 200],
            ]
        );

        $tileIdMap = [101 => $tile->id, 102 => $tile->id];
        $tileMetricMapping = [101 => [101, 102]]; // Tile 101 has both metrics

        $this->seeder->run(
            Collection::make([$parsedMetric1, $parsedMetric2]),
            $tileIdMap,
            $tileMetricMapping
        );

        // Should create one TimePeriod (same period)
        $timePeriods = TimePeriod::where('tile_id', $tile->id)->get();
        $this->assertCount(1, $timePeriods);

        // Should create two MetricDefinitions (one per metric_key)
        $metricDefinitions = MetricDefinition::where('tile_id', $tile->id)->get();
        $this->assertCount(2, $metricDefinitions);

        // Should create two MetricValues (one per metric definition)
        $metricValues = MetricValue::whereIn('time_period_id', $timePeriods->pluck('id'))->get();
        $this->assertCount(2, $metricValues);
    }

    public function test_seeder_handles_translatable_fields(): void
    {
        // Create a tile
        $tile = new Tile;
        $tile->title = ['de' => 'Test Tile', 'en' => 'Test Tile EN'];
        $tile->description = ['de' => 'Test Description', 'en' => 'Test Description EN'];
        $tile->position = 1;
        $tile->save();

        // Create parsed metric with EN translation
        $parsedMetric = new ParsedMetric(
            id: 101,
            key: 'test_metric',
            title: 'Test Kennzahl',
            titleEn: 'Test Metric',
            unit: 'Stück',
            icon: null,
            years: [
                ['year' => 2020, 'value' => 100],
            ]
        );

        $tileIdMap = [$parsedMetric->id => $tile->id];
        $tileMetricMapping = [$parsedMetric->id => [$parsedMetric->id]];

        $this->seeder->run(
            Collection::make([$parsedMetric]),
            $tileIdMap,
            $tileMetricMapping
        );

        $metricDefinition = MetricDefinition::where('tile_id', $tile->id)->first();
        $this->assertNotNull($metricDefinition);
        $this->assertEquals('Test Kennzahl', $metricDefinition->getTranslation('label', 'de'));
        $this->assertEquals('Test Metric', $metricDefinition->getTranslation('label', 'en'));
    }

    public function test_seeder_skips_metrics_without_years(): void
    {
        // Create a tile
        $tile = new Tile;
        $tile->title = ['de' => 'Test Tile', 'en' => 'Test Tile EN'];
        $tile->description = ['de' => 'Test Description', 'en' => 'Test Description EN'];
        $tile->position = 1;
        $tile->save();

        // Create parsed metric without years
        $parsedMetric = new ParsedMetric(
            id: 101,
            key: 'test_metric',
            title: 'Test Kennzahl',
            titleEn: null,
            unit: null,
            icon: null,
            years: []
        );

        $tileIdMap = [$parsedMetric->id => $tile->id];
        $tileMetricMapping = [$parsedMetric->id => [$parsedMetric->id]];

        $this->seeder->run(
            Collection::make([$parsedMetric]),
            $tileIdMap,
            $tileMetricMapping
        );

        // Should not create any TimePeriods, MetricDefinitions or MetricValues
        $this->assertEquals(0, TimePeriod::where('tile_id', $tile->id)->count());
        $this->assertEquals(0, MetricDefinition::where('tile_id', $tile->id)->count());
        $this->assertEquals(0, MetricValue::count());
    }

    public function test_seeder_converts_values_to_decimal(): void
    {
        // Create a tile
        $tile = new Tile;
        $tile->title = ['de' => 'Test Tile', 'en' => 'Test Tile EN'];
        $tile->description = ['de' => 'Test Description', 'en' => 'Test Description EN'];
        $tile->position = 1;
        $tile->save();

        // Create parsed metric with string value
        $parsedMetric = new ParsedMetric(
            id: 101,
            key: 'test_metric',
            title: 'Test Kennzahl',
            titleEn: null,
            unit: null,
            icon: null,
            years: [
                ['year' => 2020, 'value' => '123.45'],
            ]
        );

        $tileIdMap = [$parsedMetric->id => $tile->id];
        $tileMetricMapping = [$parsedMetric->id => [$parsedMetric->id]];

        $this->seeder->run(
            Collection::make([$parsedMetric]),
            $tileIdMap,
            $tileMetricMapping
        );

        $metricValue = MetricValue::first();
        $this->assertNotNull($metricValue);
        // Laravel's decimal cast returns string, so we check numeric value
        $this->assertEquals('123.45', $metricValue->value);
        $this->assertIsNumeric($metricValue->value);
        // Verify it can be converted to float
        $this->assertEquals(123.45, (float) $metricValue->value);
    }
}
