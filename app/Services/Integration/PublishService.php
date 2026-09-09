<?php

namespace App\Services\Integration;

use App\Contracts\Integration\WritableDataSourceInterface;
use App\Exceptions\Integration\ForeignProvenanceException;
use App\Models\Tile;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;

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
     * @throws RequestException on a broker write error.
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
     * Publish many Tiles to the external broker in one batch (bulk write-back).
     *
     * Each Tile is published through {@see self::publishTile()}, so the provenance
     * guard and source-hash idempotency rules are inherited unchanged. The loop is
     * fault-isolated: any single Tile that throws is recorded and the batch
     * continues, so one broker error never aborts the whole batch.
     *
     * Outcome buckets:
     *  - published: written to the broker;
     *  - skipped:   an idempotent no-op (the Tile is unchanged);
     *  - refused:   a foreign-provenance Tile that is deliberately never
     *               clobbered (ForeignProvenanceException);
     *  - failed:    any other error (broker rejection, connection failure, …),
     *               with the per-Tile message collected under `errors` keyed by
     *               the Tile's primary key.
     *
     * @param  iterable<Tile>  $tiles
     * @param  bool  $force  Force a re-publish of unchanged Tiles (passed to publishTile()).
     * @return array{published:int,skipped:int,refused:int,failed:int,errors:array<int|string,string>}
     */
    public function publishMany(iterable $tiles, bool $force = false): array
    {
        $published = 0;
        $skipped = 0;
        $refused = 0;
        $failed = 0;
        $errors = [];

        foreach ($tiles as $tile) {
            try {
                $result = $this->publishTile($tile, $force);

                if ($result->skipped) {
                    $skipped++;
                } else {
                    $published++;
                }
            } catch (ForeignProvenanceException $e) {
                // Never clobber a foreign-provenance Tile: it is a deliberate
                // refusal, bucketed separately from an unchanged skip and never
                // treated as a batch failure.
                $refused++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[$tile->getKey()] = $e->getMessage();
            }
        }

        return [
            'published' => $published,
            'skipped' => $skipped,
            'refused' => $refused,
            'failed' => $failed,
            'errors' => $errors,
        ];
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
