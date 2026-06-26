<?php

namespace App\Services\Integration;

use App\Contracts\Integration\DataMapperInterface;
use App\Models\Tile;
use Illuminate\Support\Str;

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
    // Reverse mapping: Dashboard Tile → NGSI-LD entity (write-back)
    // -----------------------------------------------------------------

    /**
     * Map a dashboard Tile to an NGSI-LD entity body for publishing to CORE.
     *
     * The produced entity round-trips with the forward mapper: name/description
     * are LanguageProperty nodes, the time-series is a `dataPoints` Property
     * (never `values` — Stellio rejects that reserved term with HTTP 400), unit
     * and timeGranularity are Property nodes, and the category
     * (when present) is a Relationship pointing at the category's external URN.
     *
     * The entity `id` reuses the Tile's `external_id` when set (so re-publishing
     * targets the same broker entity) and is otherwise minted from the Tile slug.
     *
     * @return array<string,mixed> NGSI-LD entity body (no @context — the client adds it).
     */
    public function mapTileToEntity(Tile $tile): array
    {
        $tile->loadMissing([
            'metricDefinitions.metricValues.timePeriod',
            'categories',
        ]);

        $entity = [
            'id' => $this->resolveEntityId($tile),
            'type' => SyncService::ENTITY_TYPE,
        ];

        if (($name = $this->buildLanguageProperty($tile->getTranslations('title'))) !== null) {
            $entity['name'] = $name;
        }

        if (($description = $this->buildLanguageProperty($tile->getTranslations('description'))) !== null) {
            $entity['description'] = $description;
        }

        $definition = $tile->metricDefinitions->first();

        if (($unit = $this->resolveUnit($definition)) !== null) {
            $entity['unit'] = ['type' => 'Property', 'value' => $unit];
        }

        $entity['timeGranularity'] = [
            'type' => 'Property',
            'value' => (string) ($tile->time_granularity ?: 'year'),
        ];

        $entity['dataPoints'] = [
            'type' => 'Property',
            'value' => $this->buildDataPoints($definition),
        ];

        if (($categoryUrn = $this->resolveCategoryUrn($tile)) !== null) {
            $entity['category'] = ['type' => 'Relationship', 'object' => $categoryUrn];
        }

        return $entity;
    }

    /**
     * Reuse the Tile's external_id (round-trip) or mint a URN from its slug.
     */
    private function resolveEntityId(Tile $tile): string
    {
        $externalId = $tile->external_id;

        if (is_string($externalId) && trim($externalId) !== '') {
            return $externalId;
        }

        $slug = $tile->getTranslation('slug', 'de', false)
            ?: $tile->getTranslation('slug', 'en', false)
            ?: $tile->getTranslation('title', 'de', false)
            ?: (string) $tile->id;

        $segment = Str::slug((string) $slug) ?: 'indicator';

        return 'urn:ngsi-ld:'.SyncService::ENTITY_TYPE.':'.$segment;
    }

    /**
     * Build a LanguageProperty node from a {de,en} translation array.
     *
     * Returns null when no non-empty translation exists, so empty optional
     * fields are simply omitted from the published entity.
     *
     * @param  array<string,mixed>  $translations
     * @return array{type:string,languageMap:array<string,string>}|null
     */
    private function buildLanguageProperty(array $translations): ?array
    {
        $languageMap = [];

        foreach ($translations as $locale => $value) {
            if (is_string($locale) && is_string($value) && trim($value) !== '') {
                $languageMap[$locale] = $value;
            }
        }

        if ($languageMap === []) {
            return null;
        }

        return ['type' => 'LanguageProperty', 'languageMap' => $languageMap];
    }

    /**
     * Resolve the scalar unit string from a MetricDefinition's translatable unit.
     */
    private function resolveUnit(mixed $definition): ?string
    {
        if ($definition === null) {
            return null;
        }

        $unit = $definition->getTranslations('unit');

        if (! is_array($unit)) {
            return null;
        }

        $value = $unit['de'] ?? $unit['en'] ?? (reset($unit) ?: null);

        return (is_string($value) && trim($value) !== '') ? $value : null;
    }

    /**
     * Build the dataPoints value list from a definition's metric values.
     *
     * Each entry is {period:<period_key>, value:<float|null>}, matching the
     * shape the forward mapper (mapToMetricValues) reads back, so the time-series
     * round-trips. Values are cast to float; missing values become null.
     *
     * @return array<int,array{period:string,value:float|null}>
     */
    private function buildDataPoints(mixed $definition): array
    {
        if ($definition === null) {
            return [];
        }

        return $definition->metricValues
            ->filter(fn ($value) => $value->timePeriod !== null
                && is_string($value->timePeriod->period_key)
                && $value->timePeriod->period_key !== '')
            ->map(fn ($value) => [
                'period' => (string) $value->timePeriod->period_key,
                'value' => $value->value === null ? null : (float) $value->value,
            ])
            ->values()
            ->all();
    }

    /**
     * Resolve the external URN of the Tile's first category that carries one.
     */
    private function resolveCategoryUrn(Tile $tile): ?string
    {
        foreach ($tile->categories as $category) {
            $urn = $category->external_id;

            if (is_string($urn) && trim($urn) !== '') {
                return $urn;
            }
        }

        return null;
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
