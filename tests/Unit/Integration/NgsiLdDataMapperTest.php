<?php

namespace Tests\Unit\Integration;

use App\Contracts\Integration\DataMapperInterface;
use App\Models\Category;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Services\Integration\NgsiLdDataMapper;
use ReflectionMethod;
use Tests\TestCase;

class NgsiLdDataMapperTest extends TestCase
{
    private NgsiLdDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new NgsiLdDataMapper;
    }

    /**
     * Invoke a private/protected method on the mapper.
     */
    private function invoke(string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod($this->mapper, $method);
        $ref->setAccessible(true);

        return $ref->invoke($this->mapper, ...$args);
    }

    public function test_implements_data_mapper_interface(): void
    {
        $this->assertInstanceOf(DataMapperInterface::class, $this->mapper);
    }

    // -----------------------------------------------------------------
    // extractLanguageMap
    // -----------------------------------------------------------------

    public function test_extract_language_map_from_language_property(): void
    {
        $node = [
            'type' => 'LanguageProperty',
            'languageMap' => ['de' => 'X', 'en' => 'Y'],
        ];

        $this->assertSame(['de' => 'X', 'en' => 'Y'], $this->invoke('extractLanguageMap', $node));
    }

    public function test_extract_language_map_from_plain_string(): void
    {
        $this->assertSame(['de' => 'X'], $this->invoke('extractLanguageMap', 'X'));
    }

    // -----------------------------------------------------------------
    // extractScalar
    // -----------------------------------------------------------------

    public function test_extract_scalar_from_property_node(): void
    {
        $this->assertSame(5, $this->invoke('extractScalar', ['type' => 'Property', 'value' => 5]));
    }

    public function test_extract_scalar_from_raw_value(): void
    {
        $this->assertSame(5, $this->invoke('extractScalar', 5));
    }

    // -----------------------------------------------------------------
    // extractRelationship
    // -----------------------------------------------------------------

    public function test_extract_relationship_from_relationship_node(): void
    {
        $node = ['type' => 'Relationship', 'object' => 'urn:ngsi-ld:Category:mobility'];

        $this->assertSame('urn:ngsi-ld:Category:mobility', $this->invoke('extractRelationship', $node));
    }

    public function test_extract_relationship_from_raw_string(): void
    {
        $this->assertSame('urn:ngsi-ld:Category:mobility', $this->invoke('extractRelationship', 'urn:ngsi-ld:Category:mobility'));
    }

    // -----------------------------------------------------------------
    // mapToTile
    // -----------------------------------------------------------------

    public function test_map_to_tile_produces_translatable_arrays_and_external_keys(): void
    {
        $entity = [
            'id' => 'urn:ngsi-ld:Indicator:co2-emissions',
            'type' => 'Indicator',
            'name' => [
                'type' => 'LanguageProperty',
                'languageMap' => ['de' => 'CO2-Emissionen', 'en' => 'CO2 Emissions'],
            ],
            'description' => [
                'type' => 'LanguageProperty',
                'languageMap' => ['de' => 'Beschreibung', 'en' => 'Description'],
            ],
        ];

        $result = $this->mapper->mapToTile($entity);

        $this->assertSame(['de' => 'CO2-Emissionen', 'en' => 'CO2 Emissions'], $result['title']);
        $this->assertSame(['de' => 'Beschreibung', 'en' => 'Description'], $result['description']);
        $this->assertTrue($result['is_public']);
        $this->assertSame(NgsiLdDataMapper::SOURCE_KEY, $result['external_source']);
        $this->assertSame('urn:ngsi-ld:Indicator:co2-emissions', $result['external_id']);
    }

    // -----------------------------------------------------------------
    // mapToMetricDefinition
    // -----------------------------------------------------------------

    public function test_map_to_metric_definition_derives_metric_key_from_urn_last_segment(): void
    {
        $entity = [
            'id' => 'urn:ngsi-ld:Indicator:co2-emissions',
            'name' => [
                'type' => 'LanguageProperty',
                'languageMap' => ['de' => 'CO2', 'en' => 'CO2'],
            ],
            'unit' => ['type' => 'Property', 'value' => 't'],
        ];

        $result = $this->mapper->mapToMetricDefinition($entity);

        $this->assertSame('co2_emissions', $result['metric_key']);
        $this->assertSame(['de' => 'CO2', 'en' => 'CO2'], $result['label']);
        $this->assertSame(['de' => 't', 'en' => 't'], $result['unit']);
        $this->assertSame('number', $result['indicator_type']);
        $this->assertTrue($result['is_active']);
        $this->assertSame(NgsiLdDataMapper::SOURCE_KEY, $result['external_source']);
        $this->assertSame('urn:ngsi-ld:Indicator:co2-emissions', $result['external_id']);
    }

    // -----------------------------------------------------------------
    // mapToMetricValues
    // -----------------------------------------------------------------

    public function test_map_to_metric_values_fans_out_with_null(): void
    {
        $entity = [
            'dataPoints' => [
                ['period' => '2023', 'value' => 1.5],
                ['period' => '2024', 'value' => null],
            ],
        ];

        $result = $this->mapper->mapToMetricValues($entity);

        $this->assertCount(2, $result);
        $this->assertSame(['period' => '2023', 'value' => 1.5], $result[0]);
        $this->assertSame('2024', $result[1]['period']);
        $this->assertNull($result[1]['value']);
    }

    public function test_map_to_metric_values_skips_entries_without_usable_period(): void
    {
        $entity = [
            'dataPoints' => [
                ['period' => '2023', 'value' => 1.5],
                ['value' => 9.9],                  // missing period
                ['period' => '', 'value' => 2.2],  // empty period
            ],
        ];

        $result = $this->mapper->mapToMetricValues($entity);

        $this->assertCount(1, $result);
        $this->assertSame(['period' => '2023', 'value' => 1.5], $result[0]);
    }

    public function test_map_to_metric_values_accepts_non_year_granularities(): void
    {
        $entity = [
            'dataPoints' => [
                ['period' => '2024-Q1', 'value' => 1.5],
                ['period' => '2024-03', 'value' => 2.5],
                ['period' => '2024-01-15', 'value' => 3.5],
            ],
        ];

        $result = $this->mapper->mapToMetricValues($entity);

        $this->assertSame('2024-Q1', $result[0]['period']);
        $this->assertSame('2024-03', $result[1]['period']);
        $this->assertSame('2024-01-15', $result[2]['period']);
    }

    public function test_map_to_metric_values_tolerates_legacy_year_key(): void
    {
        $entity = [
            'dataPoints' => [
                ['year' => 2023, 'value' => 1.5],
                ['year' => 'abc', 'value' => 2.2], // non-numeric year => skipped
            ],
        ];

        $result = $this->mapper->mapToMetricValues($entity);

        $this->assertCount(1, $result);
        $this->assertSame(['period' => '2023', 'value' => 1.5], $result[0]);
    }

    public function test_map_to_metric_values_tolerates_property_wrapped_list(): void
    {
        $entity = [
            'dataPoints' => [
                'type' => 'Property',
                'value' => [
                    ['period' => '2023', 'value' => 1.5],
                    ['period' => '2024', 'value' => 2.5],
                ],
            ],
        ];

        $result = $this->mapper->mapToMetricValues($entity);

        $this->assertCount(2, $result);
        $this->assertSame(['period' => '2023', 'value' => 1.5], $result[0]);
        $this->assertSame(['period' => '2024', 'value' => 2.5], $result[1]);
    }

    public function test_map_to_tile_sets_time_granularity_defaulting_to_year(): void
    {
        $base = ['id' => 'urn:ngsi-ld:Indicator:x', 'name' => 'X'];

        $this->assertSame('year', $this->mapper->mapToTile($base)['time_granularity']);

        $withQuarter = $base + ['timeGranularity' => ['type' => 'Property', 'value' => 'quarter']];
        $this->assertSame('quarter', $this->mapper->mapToTile($withQuarter)['time_granularity']);

        $unknown = $base + ['timeGranularity' => ['type' => 'Property', 'value' => 'decade']];
        $this->assertSame('year', $this->mapper->mapToTile($unknown)['time_granularity']);
    }

    // -----------------------------------------------------------------
    // mapToMetricValue
    // -----------------------------------------------------------------

    public function test_map_to_metric_value_single_pair(): void
    {
        $this->assertSame(
            ['value' => 1.5, 'is_active' => true],
            $this->mapper->mapToMetricValue(['period' => '2023', 'value' => 1.5]),
        );

        $this->assertSame(
            ['value' => null, 'is_active' => true],
            $this->mapper->mapToMetricValue(['period' => '2024']),
        );
    }

    // -----------------------------------------------------------------
    // computeSourceHash
    // -----------------------------------------------------------------

    public function test_compute_source_hash_is_stable_ignoring_volatile_keys(): void
    {
        $a = [
            'id' => 'urn:ngsi-ld:Indicator:x',
            '@context' => 'https://example.com/context-v1.jsonld',
            'observedAt' => '2024-01-01T00:00:00Z',
            'name' => ['type' => 'LanguageProperty', 'languageMap' => ['de' => 'X']],
        ];

        $b = [
            'id' => 'urn:ngsi-ld:Indicator:x',
            '@context' => 'https://example.com/context-v2.jsonld',
            'observedAt' => '2025-06-06T12:00:00Z',
            'name' => ['type' => 'LanguageProperty', 'languageMap' => ['de' => 'X']],
        ];

        $this->assertSame(
            $this->mapper->computeSourceHash($a),
            $this->mapper->computeSourceHash($b),
        );
    }

    public function test_compute_source_hash_differs_when_value_changes(): void
    {
        $a = [
            'id' => 'urn:ngsi-ld:Indicator:x',
            'name' => ['type' => 'LanguageProperty', 'languageMap' => ['de' => 'X']],
        ];

        $b = [
            'id' => 'urn:ngsi-ld:Indicator:x',
            'name' => ['type' => 'LanguageProperty', 'languageMap' => ['de' => 'Y']],
        ];

        $this->assertNotSame(
            $this->mapper->computeSourceHash($a),
            $this->mapper->computeSourceHash($b),
        );
    }

    // -----------------------------------------------------------------
    // mapToCategory
    // -----------------------------------------------------------------

    public function test_map_to_category_extracts_key_and_full_urn(): void
    {
        $entity = [
            'category' => [
                'type' => 'Relationship',
                'object' => 'urn:ngsi-ld:Category:mobility-transport',
            ],
        ];

        $result = $this->mapper->mapToCategory($entity);

        $this->assertSame('mobility_transport', $result['key']);
        $this->assertSame(['de' => 'mobility_transport'], $result['slug']);
        $this->assertTrue($result['is_active']);
        $this->assertSame(NgsiLdDataMapper::SOURCE_KEY, $result['external_source']);
        $this->assertSame('urn:ngsi-ld:Category:mobility-transport', $result['external_id']);
    }

    public function test_map_to_category_returns_empty_when_no_category(): void
    {
        $this->assertSame([], $this->mapper->mapToCategory(['id' => 'urn:ngsi-ld:Indicator:x']));
    }

    // -----------------------------------------------------------------
    // mapTileToEntity (reverse mapping, write-back)
    // -----------------------------------------------------------------

    /**
     * Build an in-memory Tile graph exposing exactly what mapTileToEntity reads.
     *
     * Genuine Eloquent models are hydrated via forceFill + setRelation so this
     * stays a pure unit test (no DB / RefreshDatabase) while mirroring the
     * eager-loaded graph: title/description/slug translations, time_granularity,
     * external_id, metricDefinitions->metricValues->timePeriod, categories.
     *
     * @param  array<string,mixed>  $overrides
     */
    private function makeTileModel(array $overrides = []): Tile
    {
        $defaults = [
            'title' => ['de' => 'CO2-Emissionen', 'en' => 'CO2 Emissions'],
            'description' => ['de' => 'Beschreibung', 'en' => 'Description'],
            'slug' => ['de' => 'co2-emissionen', 'en' => 'co2-emissions'],
            'time_granularity' => 'year',
            'external_id' => null,
            'unit' => ['de' => 't', 'en' => 't'],
            'dataPoints' => [
                ['period' => '2023', 'value' => 1.5],
                ['period' => '2024', 'value' => null],
            ],
            'categoryUrn' => null,
        ];

        $d = array_merge($defaults, $overrides);

        $tile = (new Tile)->forceFill([
            'title' => $d['title'],
            'description' => $d['description'],
            'slug' => $d['slug'],
            'time_granularity' => $d['time_granularity'],
            'external_id' => $d['external_id'],
        ]);

        $metricValues = collect($d['dataPoints'])->map(function (array $point) {
            $period = (new TimePeriod)->forceFill(['period_key' => $point['period']]);
            $value = (new MetricValue)->forceFill(['value' => $point['value']]);
            $value->setRelation('timePeriod', $period);

            return $value;
        });

        $definition = (new MetricDefinition)->forceFill(['unit' => $d['unit']]);
        $definition->setRelation('metricValues', $metricValues);
        $tile->setRelation('metricDefinitions', collect([$definition]));

        $categories = $d['categoryUrn'] === null
            ? collect()
            : collect([(new Category)->forceFill(['external_id' => $d['categoryUrn']])]);
        $tile->setRelation('categories', $categories);

        return $tile;
    }

    public function test_map_tile_to_entity_sets_type_and_language_properties(): void
    {
        $entity = $this->mapper->mapTileToEntity($this->makeTileModel());

        $this->assertSame('NachhaltigkeitsIndikator', $entity['type']);

        // name + description must be LanguageProperty with a {de,en} languageMap.
        $this->assertSame('LanguageProperty', $entity['name']['type']);
        $this->assertSame(['de' => 'CO2-Emissionen', 'en' => 'CO2 Emissions'], $entity['name']['languageMap']);
        $this->assertSame('LanguageProperty', $entity['description']['type']);
        $this->assertSame(['de' => 'Beschreibung', 'en' => 'Description'], $entity['description']['languageMap']);
    }

    public function test_map_tile_to_entity_mints_urn_from_slug_when_no_external_id(): void
    {
        $entity = $this->mapper->mapTileToEntity($this->makeTileModel(['slug' => ['de' => 'co2-emissionen']]));

        $this->assertSame('urn:ngsi-ld:NachhaltigkeitsIndikator:co2-emissionen', $entity['id']);
    }

    public function test_map_tile_to_entity_reuses_external_id_for_round_trip(): void
    {
        $entity = $this->mapper->mapTileToEntity(
            $this->makeTileModel(['external_id' => 'urn:ngsi-ld:NachhaltigkeitsIndikator:existing-id'])
        );

        $this->assertSame('urn:ngsi-ld:NachhaltigkeitsIndikator:existing-id', $entity['id']);
    }

    public function test_map_tile_to_entity_uses_data_points_never_values(): void
    {
        $entity = $this->mapper->mapTileToEntity($this->makeTileModel());

        // CRITICAL: the time-series attribute MUST be `dataPoints`,
        // never `values` (Stellio rejects the reserved `values` term with 400).
        $this->assertArrayHasKey('dataPoints', $entity);
        $this->assertArrayNotHasKey('values', $entity);

        $this->assertSame('Property', $entity['dataPoints']['type']);
        $this->assertSame(
            [
                ['period' => '2023', 'value' => 1.5],
                ['period' => '2024', 'value' => null],
            ],
            $entity['dataPoints']['value'],
        );
    }

    public function test_map_tile_to_entity_emits_unit_and_granularity_properties(): void
    {
        $entity = $this->mapper->mapTileToEntity($this->makeTileModel(['time_granularity' => 'quarter']));

        $this->assertSame('Property', $entity['unit']['type']);
        $this->assertSame('t', $entity['unit']['value']);
        $this->assertSame('Property', $entity['timeGranularity']['type']);
        $this->assertSame('quarter', $entity['timeGranularity']['value']);
    }

    public function test_map_tile_to_entity_emits_category_relationship_when_present(): void
    {
        $entity = $this->mapper->mapTileToEntity(
            $this->makeTileModel(['categoryUrn' => 'urn:ngsi-ld:Category:mobility-transport'])
        );

        $this->assertSame('Relationship', $entity['category']['type']);
        $this->assertSame('urn:ngsi-ld:Category:mobility-transport', $entity['category']['object']);
    }

    public function test_map_tile_to_entity_omits_category_when_absent(): void
    {
        $entity = $this->mapper->mapTileToEntity($this->makeTileModel(['categoryUrn' => null]));

        $this->assertArrayNotHasKey('category', $entity);
    }

    /**
     * Round-trip: publishing a Tile then reading the same entity back through the
     * forward mapper must reproduce the same logical content (title, dataPoints).
     */
    public function test_round_trip_with_forward_mapper(): void
    {
        $entity = $this->mapper->mapTileToEntity($this->makeTileModel());

        $this->assertSame(['de' => 'CO2-Emissionen', 'en' => 'CO2 Emissions'], $this->mapper->mapToTile($entity)['title']);

        $this->assertSame(
            [
                ['period' => '2023', 'value' => 1.5],
                ['period' => '2024', 'value' => null],
            ],
            $this->mapper->mapToMetricValues($entity),
        );
    }
}
