<?php

namespace App\Contracts\Integration;

interface DataMapperInterface
{
    /**
     * Map an external entity to the attributes expected by the Tile model.
     *
     * @param  array  $entity  Raw entity data from the external source.
     * @return array Tile-compatible attribute array (title, description, icon, ...).
     */
    public function mapToTile(array $entity): array;

    /**
     * Map an external entity to Category model attributes.
     */
    public function mapToCategory(array $entity): array;

    /**
     * Map an external entity to MetricDefinition model attributes.
     */
    public function mapToMetricDefinition(array $entity): array;

    /**
     * Map an external entity to MetricValue model attributes.
     */
    public function mapToMetricValue(array $entity): array;

    /**
     * Compute a deterministic hash of the entity data for change detection.
     */
    public function computeSourceHash(array $entity): string;
}
