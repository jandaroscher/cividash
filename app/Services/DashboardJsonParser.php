<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Parser for dashboard.json file structure.
 *
 * Parses the Regensburg dashboard.json and extracts:
 * - Categories (handlungsfelder)
 * - Tiles (kacheln)
 * - Relationships (lnk_handlungsfelder)
 */
class DashboardJsonParser
{
    /**
     * Parse the dashboard.json file and return structured data.
     *
     * @param string $jsonPath Path to the dashboard.json file
     * @return array{categories: Collection, tiles: Collection, links: Collection}
     * @throws \RuntimeException If file cannot be read or JSON is invalid
     */
    public function parse(string $jsonPath): array
    {
        if (! file_exists($jsonPath)) {
            throw new \RuntimeException("Dashboard JSON file not found: {$jsonPath}");
        }

        $content = file_get_contents($jsonPath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON: '.json_last_error_msg());
        }

        $config = config('seeding.json_keys', []);

        $metricsData = $data[$config['metrics'] ?? 'Kennzahlen'] ?? [];
        $datenData = $data[$config['data'] ?? 'daten'] ?? [];
        $sdgData = $data[$config['sdg'] ?? 'sdg'] ?? [];

        return [
            'categories' => $this->parseCategories($data[$config['categories'] ?? 'handlungsfelder'] ?? []),
            'tiles' => $this->parseTiles($data[$config['tiles'] ?? 'kacheln'] ?? []),
            'links' => $this->parseLinks($data[$config['tiles'] ?? 'kacheln'] ?? []),
            'metrics' => $this->parseMetrics($metricsData, $datenData),
            'sdg_ziele' => $this->parseSDGZiele($sdgData),
        ];
    }

    /**
     * Parse categories from JSON data.
     *
     * Validates category IDs (numeric keys or explicit 'id' field) and titles.
     * Skips invalid entries and logs warnings.
     *
     * @param array $categoriesData
     * @return Collection<int, ParsedCategory>
     */
    protected function parseCategories(array $categoriesData): Collection
    {
        return collect($categoriesData)
            ->map(function (array $category, string $key) {
                // Prefer explicit 'id' field if present, otherwise use key
                $id = null;
                if (isset($category['id']) && is_numeric($category['id'])) {
                    $id = (int) $category['id'];
                } elseif (is_numeric($key)) {
                    $id = (int) $key;
                } else {
                    // Non-numeric key and no explicit id field
                    Log::warning('Skipping category with invalid ID', [
                        'key' => $key,
                        'category' => $category,
                    ]);
                    return null;
                }

                // Validate and sanitize title
                $title = $category['title'] ?? null;
                if (empty($title) || ! is_string($title) || trim($title) === '') {
                    $title = 'Untitled Category';
                    Log::warning('Category has empty or invalid title, using default', [
                        'id' => $id,
                        'original_title' => $category['title'] ?? null,
                    ]);
                } else {
                    $title = trim($title);
                }

                return new ParsedCategory(
                    id: $id,
                    title: $title,
                );
            })
            ->filter(fn ($category) => $category !== null);
    }

    /**
     * Parse tiles from JSON data.
     *
     * @param array $tilesData
     * @return Collection<int, ParsedTile>
     */
    protected function parseTiles(array $tilesData): Collection
    {
        return collect($tilesData)->map(function (array $tile, string $key) {
            return new ParsedTile(
                id: (int) ($tile['id'] ?? $key),
                title: $tile['title'] ?? '',
                titleEn: $tile['title_en'] ?? null,
                description: $tile['descr'] ?? $tile['description'] ?? null,
                descriptionEn: $tile['descr_en'] ?? null,
                position: isset($tile['sortby']) ? (int) $tile['sortby'] : null,
                icon: $tile['upload_grafik'] ?? null, // Only use upload_grafik, not background_image (which belongs to background)
                backgroundText: $tile['background_text'] ?? null,
                backgroundTextEn: $tile['background_text_en'] ?? null,
                contributionText: $tile['contribution_text'] ?? null,
                contributionTextEn: $tile['contribution_text_en'] ?? null,
                sliderData: $this->extractSliderData($tile),
                categoryIds: $tile['lnk_handlungsfelder'] ?? [],
                metricIds: $tile['lnk_kennzahlen'] ?? [],
                handlungsdimension: $tile['handlungsdimension'] ?? null,
                sdgZielIds: $tile['lnk_ziele_sdg'] ?? [],
            );
        });
    }

    /**
     * Extract slider data from tile.
     *
     * Groups slider fields by index (1, 2, 3, etc.) and returns structured array.
     *
     * @param array $tile
     * @return array<int, array{title?: string, title_en?: string, text?: string, text_en?: string, image?: array|string, link?: string}>
     */
    protected function extractSliderData(array $tile): array
    {
        $sliderData = [];

        // Find all slider indices (1, 2, 3, etc.)
        $indices = [];
        foreach (array_keys($tile) as $key) {
            if (preg_match('/^slider_(text|title|image|link)_(\d+)$/', $key, $matches)) {
                $index = (int) $matches[2];
                if (! in_array($index, $indices)) {
                    $indices[] = $index;
                }
            }
        }

        // Group fields by index
        foreach ($indices as $index) {
            $item = [];

            // Extract title (DE and EN)
            if (isset($tile["slider_title_{$index}"])) {
                $item['title'] = $tile["slider_title_{$index}"];
            }
            if (isset($tile["slider_title_{$index}_en"])) {
                $item['title_en'] = $tile["slider_title_{$index}_en"];
            }

            // Extract text (DE and EN)
            if (isset($tile["slider_text_{$index}"])) {
                $item['text'] = $tile["slider_text_{$index}"];
            }
            if (isset($tile["slider_text_{$index}_en"])) {
                $item['text_en'] = $tile["slider_text_{$index}_en"];
            }

            // Extract image
            if (isset($tile["slider_image_{$index}"])) {
                $item['image'] = $tile["slider_image_{$index}"];
            }

            // Extract link
            if (isset($tile["slider_link_{$index}"])) {
                $item['link'] = $tile["slider_link_{$index}"];
            }

            // Only add if at least one field exists
            if (! empty($item)) {
                $sliderData[$index] = $item;
            }
        }

        // Sort by index and return as indexed array
        ksort($sliderData);

        return array_values($sliderData);
    }

    /**
     * Parse relationship links from tiles.
     *
     * @param array $tilesData
     * @return Collection<int, ParsedLink>
     */
    protected function parseLinks(array $tilesData): Collection
    {
        $links = collect();

        foreach ($tilesData as $tileKey => $tile) {
            $tileId = (int) ($tile['id'] ?? $tileKey);
            $categoryIds = $tile['lnk_handlungsfelder'] ?? [];

            foreach ($categoryIds as $categoryId) {
                $links->push(new ParsedLink(
                    tileId: $tileId,
                    categoryId: (int) $categoryId,
                ));
            }
        }

        return $links;
    }

    /**
     * Parse metrics (Kennzahlen) from JSON data.
     *
     * @param array $metricsData
     * @param array $datenData
     * @return Collection<int, ParsedMetric>
     */
    protected function parseMetrics(array $metricsData, array $datenData): Collection
    {
        return collect($metricsData)->map(function (array $metric, string $key) use ($datenData) {
            $metricId = (int) ($metric['id'] ?? $key);
            $metricKey = $metric['key'] ?? '';

            // Extract years from daten array
            $years = $this->extractMetricYears($metricKey, $datenData);

            // Extract icon/upload
            $icon = null;
            if (isset($metric['upload'])) {
                if (is_array($metric['upload']) && isset($metric['upload']['systemurl'])) {
                    $icon = $metric['upload']['systemurl'];
                } elseif (is_string($metric['upload']) && ! empty($metric['upload'])) {
                    $icon = $metric['upload'];
                }
            }

            // Extract indicator_type and map German values to English
            $indicatorType = null;
            if (isset($metric['indikatortyp'])) {
                // Map German values to English
                $indicatorType = match($metric['indikatortyp']) {
                    'groß' => 'big',
                    'klein' => 'small',
                    'normal' => 'small',
                    default => $metric['indikatortyp'], // Fallback for other values
                };
            } elseif (isset($metric['indicator_type'])) {
                // Already in English format
                $indicatorType = $metric['indicator_type'];
            }

            return new ParsedMetric(
                id: $metricId,
                key: $metricKey,
                title: $metric['title'] ?? '',
                titleEn: $metric['title_en'] ?? null,
                unit: $metric['unit'] ?? null,
                icon: $icon,
                years: $years,
                indicator_type: $indicatorType,
            );
        });
    }

    /**
     * Extract years data for a metric from the daten array.
     *
     * Iterates over all daten entries and extracts years where the metric key has a value.
     *
     * @param string $metricKey The key of the metric (e.g., 'straftaten')
     * @param array $datenData The daten array from JSON
     * @return array<int, array{year: int, value: mixed}> Array of year data
     */
    protected function extractMetricYears(string $metricKey, array $datenData): array
    {
        if (empty($metricKey)) {
            return [];
        }

        $years = [];

        foreach ($datenData as $datenEntry) {
            if (! is_array($datenEntry)) {
                continue;
            }

            // Check if this entry has data for this metric key
            if (! isset($datenEntry[$metricKey])) {
                continue;
            }

            $value = $datenEntry[$metricKey];

            // Skip empty or invalid values
            if ($value === null || $value === '' || $value === '00' || $value === 0) {
                continue;
            }

            // Extract year from datetime
            if (! isset($datenEntry['datetime'])) {
                continue;
            }

            $datetime = $datenEntry['datetime'];
            $year = $this->extractYearFromDateTime($datetime);

            if ($year === null) {
                continue;
            }

            $years[] = [
                'year' => $year,
                'value' => $value,
            ];
        }

        // Sort by year
        usort($years, fn ($a, $b) => $a['year'] <=> $b['year']);

        return $years;
    }

    /**
     * Extract year from datetime string.
     *
     * @param string $datetime DateTime string (e.g., "2022-12-31 00:00:00")
     * @return int|null
     */
    protected function extractYearFromDateTime(string $datetime): ?int
    {
        // Try to parse the datetime
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return null;
        }

        return (int) date('Y', $timestamp);
    }

    /**
     * Parse SDG goals (SDG-Ziele) from JSON data.
     *
     * @param array $sdgData
     * @return Collection<int, ParsedSDGZiel>
     */
    protected function parseSDGZiele(array $sdgData): Collection
    {
        return collect($sdgData)->map(function (array $sdg, string $key) {
            $sdgId = (int) ($sdg['id'] ?? $key);
            $title = $sdg['title'] ?? '';

            // Extract number from title (should be 1-17)
            $number = is_numeric($title) ? (int) $title : null;

            return new ParsedSDGZiel(
                id: $sdgId,
                number: $number,
            );
        });
    }
}

/**
 * DTO for parsed category data.
 */
class ParsedCategory
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
    ) {
    }
}

/**
 * DTO for parsed tile data.
 */
class ParsedTile
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $titleEn,
        public readonly ?string $description,
        public readonly ?string $descriptionEn,
        public readonly ?int $position,
        public readonly mixed $icon,
        public readonly ?string $backgroundText,
        public readonly ?string $backgroundTextEn,
        public readonly ?string $contributionText,
        public readonly ?string $contributionTextEn,
        public readonly array $sliderData,
        public readonly array $categoryIds,
        public readonly array $metricIds,
        public readonly ?string $handlungsdimension, // "grün", "gerecht", "produktiv"
        public readonly array $sdgZielIds,
    ) {
    }
}

/**
 * DTO for parsed relationship link.
 */
class ParsedLink
{
    public function __construct(
        public readonly int $tileId,
        public readonly int $categoryId,
    ) {
    }
}

/**
 * DTO for parsed metric (Kennzahl) data.
 */
class ParsedMetric
{
    public function __construct(
        public readonly int $id,
        public readonly string $key,
        public readonly string $title,
        public readonly ?string $titleEn,
        public readonly ?string $unit,
        public readonly mixed $icon,
        public readonly array $years, // [{year: int, value: mixed}]
        public readonly ?string $indicator_type = null,
    ) {
    }
}

/**
 * DTO for parsed SDG goal (SDG-Ziel) data.
 */
class ParsedSDGZiel
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $number, // 1-17
    ) {
    }
}

