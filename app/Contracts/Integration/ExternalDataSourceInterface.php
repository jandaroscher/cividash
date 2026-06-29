<?php

namespace App\Contracts\Integration;

interface ExternalDataSourceInterface
{
    /**
     * Test whether the external data source is reachable and credentials are valid.
     */
    public function isConnected(): bool;

    /**
     * Fetch a list of entities of the given type, optionally filtered.
     *
     * @param  string  $type  Entity type identifier (e.g. NGSI-LD entity type).
     * @param  array  $filters  Key-value pairs for server-side filtering.
     * @param  int  $limit  Maximum number of entities to return.
     * @param  int  $offset  Pagination offset.
     * @return array{entities: array, total: int}
     */
    public function fetchEntities(string $type, array $filters = [], int $limit = 100, int $offset = 0): array;

    /**
     * Fetch a single entity by its external identifier.
     */
    public function fetchEntity(string $id): ?array;

    /**
     * Return the entity types available on the remote system.
     *
     * @return string[]
     */
    public function getAvailableEntityTypes(): array;
}
