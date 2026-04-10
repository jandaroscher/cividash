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
            'external_id' => (string) ($entity['@iot.id'] ?? ''),
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

        return array_filter([
            'metric_key' => $this->slugify($observedProperty['name'] ?? $entity['name'] ?? ''),
            'label' => [
                'de' => $entity['name'] ?? '',
            ],
            'unit' => [
                'de' => $unit['symbol'] ?? '',
            ],
            'indicator_type' => 'number',
            'is_active' => true,
            'external_source' => self::SOURCE_KEY,
            'external_id' => (string) ($entity['@iot.id'] ?? ''),
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
            'value' => $entity['result'] ?? 0,
            'is_active' => true,
        ];
    }

    /**
     * Extract year from an Observation's phenomenonTime.
     */
    public function extractYear(array $observation): ?int
    {
        $time = $observation['phenomenonTime'] ?? null;

        if ($time === null) {
            return null;
        }

        return (int) substr($time, 0, 4);
    }

    public function computeSourceHash(array $entity): string
    {
        // Strip navigation links and self-links to avoid hash changes from URL differences
        $clean = array_filter($entity, fn ($key) => ! str_contains($key, '@iot.') && ! str_contains($key, 'Link'), ARRAY_FILTER_USE_KEY);

        return md5(json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9äöüß]+/u', '_', $text);

        return trim($text, '_');
    }
}
