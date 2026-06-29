<?php

namespace App\Services\Integration;

use App\Contracts\Integration\DataMapperInterface;

/**
 * Maps SensorThings entities from CIVITAS/CORE (FROST) to dashboard models.
 *
 * Mapping convention (SensorThings → Dashboard):
 *   Thing        → Tile
 *   Datastream   → MetricDefinition
 *   Observation  → MetricValue (+ TileYear derived from phenomenonTime)
 *   Thing.properties.category → Category key for assignment
 *
 * Multilingual support: German values come from the primary SensorThings
 * fields (name, description); English values from Thing.properties
 * (title_en, description_en) — a convention we define for the integration.
 */
class CivitasDataMapper implements DataMapperInterface
{
    public const SOURCE_KEY = 'civitas-core';

    /**
     * Map a SensorThings Thing to Tile model attributes.
     *
     * @param  array  $entity  A Thing object with optional expanded Locations.
     */
    public function mapToTile(array $entity): array
    {
        $props = $entity['properties'] ?? [];

        return array_filter([
            'title' => [
                'de' => $entity['name'] ?? '',
                'en' => $props['title_en'] ?? null,
            ],
            'description' => [
                'de' => $entity['description'] ?? '',
                'en' => $props['description_en'] ?? null,
            ],
            'icon' => $props['icon'] ?? null,
            'position' => $props['position'] ?? null,
            'is_public' => true,
            'external_source' => self::SOURCE_KEY,
            'external_id' => ! empty($entity['@iot.id']) ? (string) $entity['@iot.id'] : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Extract the category key from a Thing's properties.
     */
    public function extractCategoryKey(array $entity): ?string
    {
        return $entity['properties']['category'] ?? null;
    }

    /**
     * Map a SensorThings Thing to Category model attributes.
     *
     * This is used when the Thing represents a category itself, or
     * to auto-create categories from Thing.properties.category values.
     */
    public function mapToCategory(array $entity): array
    {
        $key = $this->extractCategoryKey($entity);

        if ($key === null) {
            return [];
        }

        return [
            'slug' => ['de' => $key],
            'key' => $key,
            'is_active' => true,
            'external_source' => self::SOURCE_KEY,
            'external_id' => 'category:'.$key,
        ];
    }

    /**
     * Map a SensorThings Datastream to MetricDefinition attributes.
     *
     * @param  array  $entity  A Datastream object with ObservedProperty.
     */
    public function mapToMetricDefinition(array $entity): array
    {
        $observedProperty = $entity['ObservedProperty'] ?? [];
        $unit = $entity['unitOfMeasurement'] ?? [];

        // Derive the metric key from the ObservedProperty/Datastream name.
        // For malformed datastreams with no usable name the slug is empty,
        // which would persist an ambiguous blank key — fall back to the stable
        // @iot.id, or omit the key entirely (array_filter drops null) so the
        // record is rejected downstream rather than saved with key = ''.
        $metricKey = $this->slugify($observedProperty['name'] ?? $entity['name'] ?? '');
        if ($metricKey === '') {
            $metricKey = ! empty($entity['@iot.id'])
                ? 'datastream_'.$this->slugify((string) $entity['@iot.id'])
                : null;
        }

        return array_filter([
            'metric_key' => $metricKey,
            'label' => [
                'de' => $entity['name'] ?? '',
            ],
            'unit' => [
                'de' => $unit['symbol'] ?? '',
            ],
            'indicator_type' => 'number',
            'is_active' => true,
            'external_source' => self::SOURCE_KEY,
            'external_id' => ! empty($entity['@iot.id']) ? (string) $entity['@iot.id'] : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Map a SensorThings Observation to MetricValue attributes.
     *
     * @param  array  $entity  An Observation object.
     */
    public function mapToMetricValue(array $entity): array
    {
        return [
            'value' => $entity['result'] ?? null,
            'is_active' => true,
        ];
    }

    /**
     * Fan out an entity's time-series into a list of ['year'=>int,'value'=>float|null] pairs.
     *
     * SensorThings exposes time-series as individual Observations, which the
     * sync maps per-observation (via mapToMetricValue + extractYear) rather
     * than as a bundled list on the Datastream. This method therefore returns
     * an empty array; it exists solely to satisfy the DataMapperInterface
     * contract for the SensorThings fallback driver.
     */
    public function mapToMetricValues(array $entity): array
    {
        return [];
    }

    /**
     * Extract year from an Observation's phenomenonTime.
     */
    public function extractYear(array $observation): ?int
    {
        $time = $observation['phenomenonTime'] ?? null;

        if (! is_string($time) || strlen($time) < 4 || ! ctype_digit(substr($time, 0, 4))) {
            return null;
        }

        return (int) substr($time, 0, 4);
    }

    public function computeSourceHash(array $entity): string
    {
        $clean = $this->normalizeForHash($entity);

        return md5(json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Recursively strip @iot.* and *Link keys, sort associative arrays by key.
     */
    private function normalizeForHash(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && (str_contains($key, '@iot.') || str_ends_with($key, 'Link'))) {
                continue;
            }

            $result[$key] = is_array($value) ? $this->normalizeForHash($value) : $value;
        }

        if (array_is_list($result)) {
            usort($result, fn ($a, $b) => json_encode($a) <=> json_encode($b));
        } else {
            ksort($result);
        }

        return $result;
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9äöüß]+/u', '_', $text);

        return trim($text, '_');
    }
}
