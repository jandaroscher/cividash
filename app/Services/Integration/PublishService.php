<?php

namespace App\Services\Integration;

use App\Contracts\Integration\WritableDataSourceInterface;
use App\Exceptions\Integration\ForeignProvenanceException;
use App\Models\Tile;

/**
 * Publishes a dashboard-authored indicator (a Tile + its metrics) into
 * CIVITAS/CORE as an NGSI-LD entity (write-back).
 *
 * Provenance & idempotency rules:
 *  - A Tile owned by a DIFFERENT external source than civitas-core is refused
 *    (never clobber foreign provenance). Locally-authored Tiles (external_source
 *    null) and civitas-core Tiles are publishable.
 *  - On success the Tile is stamped with provenance (external_source, external_id,
 *    source_hash, last_synced_at). Re-publishing identical content is an
 *    idempotent no-op unless $force is set.
 *  - Every write is scoped to the Tile's tenant.
 *
 * Only an explicit user action (the Filament "publish" button) invokes this, so
 * the live broker is only written when the editor deliberately publishes.
 */
class PublishService
{
    public function __construct(
        private readonly WritableDataSourceInterface $client,
        private readonly NgsiLdDataMapper $mapper,
    ) {}

    /**
     * Publish a single Tile to the external broker.
     *
     * @throws ForeignProvenanceException when the Tile belongs to a foreign source.
     * @throws \Illuminate\Http\Client\RequestException on a broker write error.
     */
    public function publishTile(Tile $tile, bool $force = false): PublishResult
    {
        throw_if(is_null($tile->tenant_id), \InvalidArgumentException::class, 'Cannot publish a tile without a tenant.');

        $this->guardProvenance($tile);

        $entity = $this->mapper->mapTileToEntity($tile);
        $hash = $this->mapper->computeSourceHash($entity);
        $externalId = (string) $entity['id'];

        // Idempotency: skip an unchanged re-publish unless explicitly forced.
        if (! $force
            && $tile->external_source === NgsiLdDataMapper::SOURCE_KEY
            && $tile->source_hash === $hash) {
            return PublishResult::skipped($externalId);
        }

        $this->client->upsertEntity($entity);

        $this->stampProvenance($tile, $externalId, $hash);

        return PublishResult::published($externalId);
    }

    /**
     * Refuse to publish a Tile owned by a foreign external source.
     */
    private function guardProvenance(Tile $tile): void
    {
        $source = $tile->external_source;

        if ($source !== null && $source !== NgsiLdDataMapper::SOURCE_KEY) {
            throw ForeignProvenanceException::forSource((string) $source);
        }
    }

    /**
     * Stamp provenance columns on the Tile after a successful publish.
     *
     * Provenance columns are guarded (not fillable), so they are written via
     * a tenant-scoped direct UPDATE — mirroring the SyncService pattern of
     * explicit tenant_id scoping rather than relying on the global tenant scope.
     */
    private function stampProvenance(Tile $tile, string $externalId, string $hash): void
    {
        $now = now();

        DB::transaction(function () use ($tile, $externalId, $hash, $now) {
            Tile::withoutGlobalScopes()
                ->where('tenant_id', $tile->tenant_id)
                ->whereKey($tile->getKey())
                ->update([
                    'external_source' => NgsiLdDataMapper::SOURCE_KEY,
                    'external_id' => $externalId,
                    'source_hash' => $hash,
                    'last_synced_at' => $now,
                ]);
        });

        $tile->forceFill([
            'external_source' => NgsiLdDataMapper::SOURCE_KEY,
            'external_id' => $externalId,
            'source_hash' => $hash,
            'last_synced_at' => $now,
        ])->syncOriginal();
    }
}
