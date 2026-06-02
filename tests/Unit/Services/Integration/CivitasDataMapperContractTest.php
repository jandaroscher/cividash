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
}
