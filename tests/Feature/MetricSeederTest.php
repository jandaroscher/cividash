<?php

namespace Tests\Feature;

use App\Models\Metric;
use App\Models\Tile;
use App\Models\TileYear;
use App\Services\ParsedMetric;
use Database\Seeders\MetricSeeder;
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
        $this->seeder = new MetricSeeder();
    }

    public function test_seeder_creates_tile_years_and_metrics(): void
    {
        // Create a tile
        $tile = new Tile();
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

        // Check TileYears were created
        $tileYears = TileYear::where('tile_id', $tile->id)->get();
        $this->assertCount(3, $tileYears);

        // Check Metrics were created
        $metrics = Metric::whereIn('tile_year_id', $tileYears->pluck('id'))->get();
        $this->assertCount(3, $metrics);

        // Check first metric
        $firstMetric = $metrics->first();
        $this->assertEquals('Test Kennzahl', $firstMetric->getTranslation('label', 'de'));
        $this->assertEquals('Test Metric', $firstMetric->getTranslation('label', 'en'));
        $this->assertEquals(100.0, $firstMetric->value);
        $this->assertEquals('Stück', $firstMetric->getTranslation('unit', 'de'));
        $this->assertEquals('seeds/metrics/metric.png', $firstMetric->icon);
    }

    public function test_seeder_is_idempotent(): void
    {
        // Create a tile
        $tile = new Tile();
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

        $firstRunTileYearCount = TileYear::where('tile_id', $tile->id)->count();
        $firstRunMetricCount = Metric::count();

        $this->seeder->run(
            Collection::make([$parsedMetric]),
            $tileIdMap,
            $tileMetricMapping
        );

        // Should not create duplicates
        $this->assertEquals($firstRunTileYearCount, TileYear::where('tile_id', $tile->id)->count());
        $this->assertEquals($firstRunMetricCount, Metric::count());
    }

    public function test_seeder_handles_multiple_metrics_per_tile(): void
    {
        // Create a tile
        $tile = new Tile();
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

        // Should create one TileYear (same year)
        $tileYears = TileYear::where('tile_id', $tile->id)->get();
        $this->assertCount(1, $tileYears);

        // Should create two Metrics (one per metric)
        $metrics = Metric::whereIn('tile_year_id', $tileYears->pluck('id'))->get();
        $this->assertCount(2, $metrics);
    }

    public function test_seeder_handles_translatable_fields(): void
    {
        // Create a tile
        $tile = new Tile();
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

        $metric = Metric::first();
        $this->assertEquals('Test Kennzahl', $metric->getTranslation('label', 'de'));
        $this->assertEquals('Test Metric', $metric->getTranslation('label', 'en'));
    }

    public function test_seeder_skips_metrics_without_years(): void
    {
        // Create a tile
        $tile = new Tile();
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

        // Should not create any TileYears or Metrics
        $this->assertEquals(0, TileYear::where('tile_id', $tile->id)->count());
        $this->assertEquals(0, Metric::count());
    }

    public function test_seeder_converts_values_to_decimal(): void
    {
        // Create a tile
        $tile = new Tile();
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

        $metric = Metric::first();
        $this->assertEquals(123.45, $metric->value);
        $this->assertIsFloat($metric->value);
    }
}

