<?php

namespace App\Contracts\Integration;

/**
 * Write capability for external data sources that support publishing entities
 * back to the broker.
 *
 * Segregated from ExternalDataSourceInterface (read) so that read-only drivers
 * (e.g. SensorThings/FROST) are never expected to implement writes. Only the
 * NGSI-LD client implements this — write-back targets a Stellio/NGSI-LD broker.
 */
interface WritableDataSourceInterface
{
    /**
     * Create or update an NGSI-LD entity on the broker (upsert semantics).
     *
     * Implementations attempt a create first and fall back to an attribute
     * update when the entity already exists, so callers get idempotent upsert
     * behaviour without inspecting existence beforehand.
     *
     * @param  array<string,mixed>  $entity  NGSI-LD entity body. Must contain `id` and `type`.
     */
    public function upsertEntity(array $entity): void;
}
