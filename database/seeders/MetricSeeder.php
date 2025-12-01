<?php

namespace Database\Seeders;

use App\Models\Metric;
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

                // Process each year for this metric
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

                    // Find or create Metric
                    $labelDe = $parsedMetric->title;
                    $labelEn = $parsedMetric->titleEn;

                    // Use metric key as unique identifier for idempotency
                    // Generate metric_key from parsed metric key (stable identifier)
                    $metricKey = $parsedMetric->key ?? \Illuminate\Support\Str::slug($labelDe);

                    // Lookup by metric_key and tile_year_id (stable, idempotent)
                    $metric = Metric::where('tile_year_id', $tileYear->id)
                        ->where('metric_key', $metricKey)
                        ->first();

                    if (! $metric) {
                        $metric = new Metric();
                        $metric->tile_year_id = $tileYear->id;
                        $metric->metric_key = $metricKey;
                        $metric->label = [
                            'de' => $labelDe,
                            'en' => $labelEn,
                        ];
                        $metric->value = $decimalValue;
                        $metric->unit = $parsedMetric->unit ? [
                            'de' => $parsedMetric->unit,
                            'en' => null, // Units typically don't have EN translations
                        ] : null;
                        // Download icon if available
                        $iconPath = $this->downloadIcon($parsedMetric->icon);
                        $metric->icon = $iconPath;
                        $metric->save();
                    } else {
                        // Update if values changed (idempotent)
                        $needsUpdate = false;

                        // Ensure metric_key is set (for existing records that might not have it)
                        if (empty($metric->metric_key)) {
                            $metric->metric_key = $metricKey;
                            $needsUpdate = true;
                        }

                        if ($metric->getTranslation('label', 'de') !== $labelDe) {
                            $metric->setTranslation('label', 'de', $labelDe);
                            $needsUpdate = true;
                        }
                        if ($labelEn !== null && $metric->getTranslation('label', 'en') !== $labelEn) {
                            $metric->setTranslation('label', 'en', $labelEn);
                            $needsUpdate = true;
                        }
                        if ($metric->value !== $decimalValue) {
                            $metric->value = $decimalValue;
                            $needsUpdate = true;
                        }
                        if ($parsedMetric->unit && $metric->getTranslation('unit', 'de') !== $parsedMetric->unit) {
                            $metric->setTranslation('unit', 'de', $parsedMetric->unit);
                            $needsUpdate = true;
                        }
                        $newIcon = $this->downloadIcon($parsedMetric->icon);
                        if ($metric->icon !== $newIcon) {
                            $metric->icon = $newIcon;
                            $needsUpdate = true;
                        }

                        if ($needsUpdate) {
                            $metric->save();
                        }
                    }

                    if ($this->command) {
                        $this->command->info("Metric seeded: {$labelDe} for year {$year} (Tile: {$tile->getTranslation('title', 'de')})");
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

