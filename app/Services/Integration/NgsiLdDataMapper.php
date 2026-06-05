<?php

namespace App\Services\Integration;

use App\Contracts\Integration\DataMapperInterface;

/**
 * Maps ETSI NGSI-LD entities from CIVITAS/CORE to dashboard models.
 *
 * Mapping convention (NGSI-LD → Dashboard):
 *   Indicator (entity)        → Tile + MetricDefinition
 *   Indicator.dataPoints[]    → MetricValue (+ TimePeriod derived from the period_key)
 *   Indicator.timeGranularity → Tile.time_granularity
 *   Indicator.category (rel)  → Category key for assignment
 *
 * NGSI-LD encodes attributes as typed nodes: Property ({type,value}),
 * LanguageProperty ({type,languageMap}) and Relationship ({type,object}).
 * This mapper unwraps those nodes while tolerating already-flattened
 * (raw scalar/string) values for resilience across broker variants.
 */
class NgsiLdDataMapper implements DataMapperInterface
{
    public const SOURCE_KEY = 'civitas-core';

    /**
     * Keys that change between fetches without representing a content change.
     */
    private const VOLATILE_KEYS = ['@context', 'observedAt', 'modifiedAt', 'createdAt', 'instanceId'];

    /**
     * Map an NGSI-LD Indicator entity to Tile model attributes.
     */
    public function mapToTile(array $entity): array
    {
        return array_filter([
            'title' => $this->extractLanguageMap($entity['name'] ?? null) ?: null,
            'description' => $this->extractLanguageMap($entity['description'] ?? null) ?: null,
            'is_public' => true,
            'time_granularity' => $this->extractGranularity($entity),
            'external_source' => self::SOURCE_KEY,
            'external_id' => isset($entity['id']) ? (string) $entity['id'] : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Map an NGSI-LD Indicator entity to Category model attributes.
     *
     * The category is read from the entity's `category` relationship; the
     * slugified last URN segment becomes the key, while the full URN is
     * preserved as the external id.
     */
    public function mapToCategory(array $entity): array
    {
        $urn = $this->extractRelationship($entity['category'] ?? null);

        if (! is_string($urn) || $urn === '') {
            return [];
        }

        $key = $this->slugify($this->lastUrnSegment($urn));

        return [
            'slug' => ['de' => $key],
            'key' => $key,
            'is_active' => true,
            'external_source' => self::SOURCE_KEY,
            'external_id' => $urn,
        ];
    }

    /**
     * Map an NGSI-LD Indicator entity to MetricDefinition model attributes.
     */
    public function mapToMetricDefinition(array $entity): array
    {
        $id = isset($entity['id']) ? (string) $entity['id'] : '';
        $metricKey = $this->slugify($this->lastUrnSegment($id));
        $unit = $this->extractScalar($entity['unit'] ?? null);

        return array_filter([
            'metric_key' => $metricKey !== '' ? $metricKey : null,
            'label' => $this->extractLanguageMap($entity['name'] ?? null) ?: null,
            'unit' => $unit !== null ? ['de' => (string) $unit, 'en' => (string) $unit] : null,
            'indicator_type' => 'number',
            'is_active' => true,
            'external_source' => self::SOURCE_KEY,
            'external_id' => $id !== '' ? $id : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Map a single ['year'=>..,'value'=>..] pair to MetricValue attributes.
     */
    public function mapToMetricValue(array $entity): array
    {
        return [
            'value' => $entity['value'] ?? null,
            'is_active' => true,
        ];
    }

    /**
     * Fan out an entity's time-series into a list of ['period'=>string,'value'=>float|null] pairs.
     *
     * The `period` is a period_key (ISO) whose format depends on the entity's
     * timeGranularity: 'YYYY', 'YYYY-Qn', 'YYYY-MM', 'YYYY-Wnn' or
     * 'YYYY-MM-DD'. Tolerates a Property-wrapped `dataPoints` node
     * ({type:Property,value:[...]}) and a legacy numeric `year` key (mapped to a
     * yearly period_key). Entries without a usable period are skipped.
     *
     * NOTE: the time-series attribute is `dataPoints`, NOT `values`. Under the
     * NGSI-LD core context `values` expands to the reserved term hasValues, which
     * Stellio — and therefore production CORE — rejects with HTTP 400. See
     * docker/civitas/v1.6.2/README.md.
     */
    public function mapToMetricValues(array $entity): array
    {
        $values = $entity['dataPoints'] ?? [];

        // Unwrap a Property node: {"type":"Property","value":[...]}.
        if (is_array($values) && ! array_is_list($values) && array_key_exists('value', $values)) {
            $values = $values['value'];
        }

        if (! is_array($values)) {
            return [];
        }

        $pairs = [];

        foreach ($values as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $period = $this->extractPeriodKey($entry);

            if ($period === null) {
                continue;
            }

            $value = $entry['value'] ?? null;

            $pairs[] = [
                'period' => $period,
                'value' => $value === null ? null : (float) $value,
            ];
        }

        return $pairs;
    }

    /**
     * Derive a period_key from a single data point.
     *
     * Accepts a non-empty string `period` (canonical period_key) or a numeric
     * `period`/`year` (legacy yearly time-series, stringified to a 4-digit year
     * key). Returns null when neither yields a usable key.
     */
    private function extractPeriodKey(array $entry): ?string
    {
        $period = $entry['period'] ?? null;

        if (is_string($period) && trim($period) !== '') {
            return trim($period);
        }

        if (is_int($period) || (is_float($period) && $period == (int) $period)) {
            return (string) (int) $period;
        }

        $year = $entry['year'] ?? null;

        if ($year !== null && is_numeric($year)) {
            return (string) (int) $year;
        }

        return null;
    }

    /**
     * Extract the tile-level time granularity.
     *
     * Reads `timeGranularity` (Property or raw scalar) and validates it against
     * the supported set; defaults to 'year' when absent or unrecognised.
     */
    private function extractGranularity(array $entity): string
    {
        $raw = $this->extractScalar($entity['timeGranularity'] ?? null);
        $value = is_string($raw) ? strtolower(trim($raw)) : '';

        return in_array($value, ['year', 'quarter', 'month', 'week', 'day'], true) ? $value : 'year';
    }

    public function computeSourceHash(array $entity): string
    {
        $clean = $this->normalizeForHash($entity);

        return md5(json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    // -----------------------------------------------------------------
    // NGSI-LD node extraction helpers
    // -----------------------------------------------------------------

    /**
     * Extract a scalar value from an NGSI-LD node.
     *
     * Handles raw scalars, {value}, {@value} and {languageMap} (first entry).
     */
    private function extractScalar(mixed $node, mixed $default = null): mixed
    {
        if ($node === null) {
            return $default;
        }

        if (! is_array($node)) {
            return $node;
        }

        if (array_key_exists('value', $node)) {
            return $node['value'];
        }

        if (array_key_exists('@value', $node)) {
            return $node['@value'];
        }

        if (isset($node['languageMap']) && is_array($node['languageMap']) && $node['languageMap'] !== []) {
            return reset($node['languageMap']);
        }

        return $default;
    }

    /**
     * Extract a {de,en} language map from an NGSI-LD node.
     *
     * Handles LanguageProperty.languageMap, {@language,@value}, a
     * Property whose value is itself a {de,en} map, a compact {de,en}
     * object and a plain string (=> ['de'=>string]). Null entries are
     * filtered out.
     *
     * @return array<string,string>
     */
    private function extractLanguageMap(mixed $node): array
    {
        if ($node === null) {
            return [];
        }

        // Plain string => German default.
        if (is_string($node)) {
            return ['de' => $node];
        }

        if (! is_array($node)) {
            return ['de' => (string) $node];
        }

        // LanguageProperty: { type, languageMap: { de, en } }.
        if (isset($node['languageMap']) && is_array($node['languageMap'])) {
            return array_filter($node['languageMap'], fn ($v) => $v !== null);
        }

        // Expanded JSON-LD value object: { @language, @value }.
        if (isset($node['@language']) && array_key_exists('@value', $node)) {
            return array_filter([$node['@language'] => $node['@value']], fn ($v) => $v !== null);
        }

        // Property whose value is itself a language map: { value: { de, en } }.
        if (isset($node['value']) && is_array($node['value'])) {
            return array_filter($node['value'], fn ($v) => $v !== null);
        }

        // Property with a scalar value => German default.
        if (array_key_exists('value', $node)) {
            return $node['value'] === null ? [] : ['de' => (string) $node['value']];
        }

        // Compact { de, en } object.
        return array_filter($node, fn ($v) => $v !== null);
    }

    /**
     * Extract the object reference from an NGSI-LD Relationship node.
     *
     * Handles a raw string, {object} and {value}.
     */
    private function extractRelationship(mixed $node): ?string
    {
        if ($node === null) {
            return null;
        }

        if (is_string($node)) {
            return $node;
        }

        if (! is_array($node)) {
            return null;
        }

        if (isset($node['object']) && is_string($node['object'])) {
            return $node['object'];
        }

        if (isset($node['value']) && is_string($node['value'])) {
            return $node['value'];
        }

        return null;
    }

    /**
     * Return the last colon-separated segment of an NGSI-LD URN.
     */
    private function lastUrnSegment(string $urn): string
    {
        $parts = explode(':', $urn);

        return (string) end($parts);
    }

    /**
     * Recursively strip volatile keys and sort assoc arrays / lists.
     */
    private function normalizeForHash(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, self::VOLATILE_KEYS, true)) {
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
