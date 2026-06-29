<?php

namespace Tests\Unit\Services\Integration;

use App\Contracts\Integration\DataMapperInterface;
use App\Services\Integration\CivitasDataMapper;
use Tests\TestCase;

class CivitasDataMapperContractTest extends TestCase
{
    private CivitasDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new CivitasDataMapper;
    }

    public function test_implements_data_mapper_interface(): void
    {
        $this->assertInstanceOf(DataMapperInterface::class, $this->mapper);
    }

    public function test_map_to_metric_values_returns_empty_array_for_sensorthings(): void
    {
        // SensorThings observations are fanned out per-observation elsewhere,
        // so this fallback mapper returns no time-series pairs here.
        $result = $this->mapper->mapToMetricValues([
            'result' => 42,
            'phenomenonTime' => '2024-01-01T00:00:00Z',
        ]);

        $this->assertSame([], $result);
    }

    public function test_map_to_metric_values_signature_returns_array(): void
    {
        $this->assertIsArray($this->mapper->mapToMetricValues([]));
    }

    public function test_metric_key_falls_back_to_iot_id_when_name_is_blank(): void
    {
        $result = $this->mapper->mapToMetricDefinition([
            '@iot.id' => 42,
            'name' => '',
            'ObservedProperty' => ['name' => ''],
        ]);

        $this->assertSame('datastream_42', $result['metric_key']);
    }

    public function test_metric_key_omitted_when_name_and_iot_id_are_missing(): void
    {
        // No usable name and no @iot.id: the key must not be persisted as ''.
        $result = $this->mapper->mapToMetricDefinition([
            'name' => '',
            'ObservedProperty' => ['name' => '   '],
        ]);

        $this->assertArrayNotHasKey('metric_key', $result);
    }

    public function test_metric_key_derived_from_observed_property_name(): void
    {
        $result = $this->mapper->mapToMetricDefinition([
            '@iot.id' => 7,
            'name' => 'PV-Leistung',
            'ObservedProperty' => ['name' => 'CO2 pro Kopf'],
        ]);

        $this->assertSame('co2_pro_kopf', $result['metric_key']);
    }
}
