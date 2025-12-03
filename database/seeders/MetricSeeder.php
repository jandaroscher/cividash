<?php

namespace Database\Seeders;

use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tile;
use App\Models\TileYear;
use App\Services\MediaDownloadService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Seeder for Metrics (Kennzahlen) from dashboard.json.
 *
 * Seeds TileYear and Metric entries based on Kennzahlen data and years from daten array.
 * Supports idempotent upserts.
 */
class MetricSeeder extends Seeder
{
    protected MediaDownloadService $mediaDownloadService;

    public function __construct()
    {
        $this->mediaDownloadService = new MediaDownloadService();
    }
    /**
     * Run the metric seeder.
     *
     * @param Collection<int, \App\Services\ParsedMetric> $metrics
     * @param array<string, int> $tileIdMap Map of original tile ID to database ID
     * @param array<int, array<int>> $tileMetricMapping Map of tile ID to array of metric IDs
     * @return void
     */
    public function run(Collection $metrics, array $tileIdMap, array $tileMetricMapping): void
    {
        // Create a map of metric ID to ParsedMetric for quick lookup
        $metricMap = [];
        foreach ($metrics as $metric) {
            $metricMap[$metric->id] = $metric;
        }

        // Process each tile that has metrics
        foreach ($tileMetricMapping as $originalTileId => $metricIds) {
            $tileId = $tileIdMap[$originalTileId] ?? null;
            if (! $tileId) {
                continue; // Tile not found, skip
            }

            $tile = Tile::find($tileId);
            if (! $tile) {
                continue;
            }

            // Process each metric for this tile
            foreach ($metricIds as $metricId) {
                $parsedMetric = $metricMap[$metricId] ?? null;
                if (! $parsedMetric || empty($parsedMetric->years)) {
                    continue; // Metric not found or has no years
                }

                $labelDe = $parsedMetric->title;
                $labelEn = $parsedMetric->titleEn;

                // Generate metric_key from parsed metric key (stable identifier)
                $metricKey = $parsedMetric->key ?? \Illuminate\Support\Str::slug($labelDe);

                // 1. Create or update MetricDefinition (once per tile)
                $definition = MetricDefinition::updateOrCreate(
                    [
                        'tile_id' => $tileId,
                        'metric_key' => $metricKey,
                    ],
                    [
                        'label' => [
                            'de' => $labelDe,
                            'en' => $labelEn,
                        ],
                        'unit' => $parsedMetric->unit ? [
                            'de' => $parsedMetric->unit,
                            'en' => null, // Units typically don't have EN translations
                        ] : null,
                        'icon' => $this->downloadIcon($parsedMetric->icon),
                        'indicator_type' => $parsedMetric->indicator_type ?? 'small',
                    ]
                );

                // 2. Create or update MetricValues for all years
                foreach ($parsedMetric->years as $yearData) {
                    $year = (int) $yearData['year'];
                    $value = $yearData['value'];

                    // Convert value to decimal
                    $decimalValue = $this->convertToDecimal($value);

                    // Find or create TileYear
                    $tileYear = TileYear::firstOrCreate(
                        [
                            'tile_id' => $tileId,
                            'year' => $year,
                        ],
                        [
                            'sort' => 0,
                        ]
                    );

                    // Create or update MetricValue
                    MetricValue::updateOrCreate(
                        [
                            'metric_definition_id' => $definition->id,
                            'tile_year_id' => $tileYear->id,
                        ],
                        [
                            'value' => $decimalValue,
                        ]
                    );

                    if ($this->command) {
                        $this->command->info("Metric value seeded: {$labelDe} for year {$year} (Tile: {$tile->getTranslation('title', 'de')})");
                    }
                }
            }
        }
    }

    /**
     * Convert a value to decimal format.
     *
     * @param mixed $value
     * @return float
     */
    protected function convertToDecimal(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        // Try to extract number from string
        if (is_string($value)) {
            // Remove non-numeric characters except decimal point and minus
            $cleaned = preg_replace('/[^0-9.-]/', '', $value);
            if (is_numeric($cleaned)) {
                return (float) $cleaned;
            }
        }

        return 0.0;
    }

    /**
     * Download icon and return local path.
     *
     * @param mixed $iconData
     * @return string|null
     */
    protected function downloadIcon(mixed $iconData): ?string
    {
        $systemUrl = $this->mediaDownloadService->extractSystemUrl($iconData);
        if (empty($systemUrl)) {
            return null;
        }

        $downloadedPath = $this->mediaDownloadService->downloadFile($systemUrl, 'metrics');
        if ($downloadedPath && $this->command) {
            $this->command->info("Downloaded metric icon: {$downloadedPath}");
        } elseif (! $downloadedPath && $this->command && $systemUrl) {
            $this->command->warn("Failed to download metric icon: {$systemUrl}");
        }

        return $downloadedPath;
    }
}

