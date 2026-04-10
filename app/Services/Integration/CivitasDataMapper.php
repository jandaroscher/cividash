<?php

namespace App\Services\Integration;

use App\Contracts\Integration\DataMapperInterface;

/**
 * Maps NGSI-LD entities from CIVITAS/CORE to the dashboard's internal models.
 *
 * The concrete field mapping depends on the NGSI-LD data model agreed upon
 * with the CIVITAS/CORE community. This stub provides the scaffolding;
 * the mapping rules will be populated once the entity types for
 * sustainability indicators are defined.
 */
class CivitasDataMapper implements DataMapperInterface
{
    public function mapToTile(array $entity): array
    {
        // TODO: Implement once NGSI-LD entity model for tiles is defined.
        //
        // Expected mapping pattern:
        //   'title'       => ['de' => $entity['name']['value'], 'en' => ...],
        //   'description' => ['de' => $entity['description']['value'], ...],
        //   'icon'        => $entity['icon']['value'] ?? null,
        //   'external_source' => 'civitas-core',
        //   'external_id'     => $entity['id'],

        return [];
    }

    public function mapToCategory(array $entity): array
    {
        // TODO: Implement once NGSI-LD entity model for categories is defined.
        return [];
    }

    public function mapToMetricDefinition(array $entity): array
    {
        // TODO: Implement once NGSI-LD entity model for metrics is defined.
        return [];
    }

    public function mapToMetricValue(array $entity): array
    {
        // TODO: Implement once NGSI-LD entity model for metric values is defined.
        return [];
    }

    public function computeSourceHash(array $entity): string
    {
        return md5(json_encode($entity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
