<?php

namespace Tests\Unit\Integration;

use App\Contracts\Integration\DataMapperInterface;
use App\Services\Integration\NgsiLdDataMapper;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

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
}
