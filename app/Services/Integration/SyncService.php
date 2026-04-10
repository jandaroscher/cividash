<?php

namespace App\Services\Integration;

use App\Contracts\Integration\DataMapperInterface;
use App\Contracts\Integration\ExternalDataSourceInterface;
use App\Contracts\Integration\SyncServiceInterface;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\MetricDefinition;
use App\Models\Tenant;
use App\Models\Tile;
use App\Settings\IntegrationSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates synchronisation between an external data source and the dashboard.
 *
 * Uses last_synced_at and source_hash on models to perform incremental updates.
 * Entities that have not changed since the last sync are skipped.
 */
class SyncService implements SyncServiceInterface
{
    public function __construct(
        private readonly ExternalDataSourceInterface $source,
        private readonly DataMapperInterface $mapper,
    ) {}

    public function syncAll(Tenant $tenant, bool $force = false, bool $dryRun = false): SyncResult
    {
        // TODO: Implement full sync loop.
        //
        // High-level algorithm:
        // 1. Fetch all entities from the external source (paginated).
        // 2. For each entity:
        //    a. Compute source_hash via $this->mapper->computeSourceHash().
        //    b. Look up local record by external_id + external_source.
        //    c. If not found → create (unless $dryRun).
        //    d. If found and source_hash differs → update (unless $dryRun).
        //    e. If found and source_hash matches → skip.
        // 3. Optionally delete local records whose external_id no longer exists upstream.
        // 4. Update last_synced_at on all touched records.
        // 5. Return SyncResult with counts.

        Log::warning('CIVITAS sync stub invoked — not yet implemented.', [
            'tenant' => $tenant->slug,
            'force' => $force,
            'dry_run' => $dryRun,
        ]);

        throw new \RuntimeException('CIVITAS sync is not yet implemented.');
    }

    public function syncEntity(Tenant $tenant, string $externalId): SyncResult
    {
        // TODO: Implement single-entity sync.

        $entity = $this->source->fetchEntity($externalId);

        if ($entity === null) {
            return new SyncResult(failed: 1, errors: ["Entity {$externalId} not found in external source."]);
        }

        return new SyncResult(failed: 1, errors: ['Single-entity sync not yet implemented.']);
    }

    public function getLastSyncStatus(Tenant $tenant): SyncStatus
    {
        $settings = rescue(fn () => app(IntegrationSettings::class), null, false);
        $apiUrl = $settings?->api_url ?: config('integrations.civitas.api_url');
        $isConfigured = ! empty($apiUrl);

        $lastSyncedAt = collect([
            Tile::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNotNull('external_source')->max('last_synced_at'),
            Category::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNotNull('external_source')->max('last_synced_at'),
            CategoryGroup::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNotNull('external_source')->max('last_synced_at'),
            MetricDefinition::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNotNull('external_source')->max('last_synced_at'),
        ])->filter()->max();

        // Cache the connectivity probe to avoid blocking page renders.
        // The "Test Connection" action in ManageIntegrations probes live.
        $isConnected = $isConfigured && Cache::remember(
            'integration:connectivity:'.$tenant->id,
            60,
            fn () => $this->source->isConnected(),
        );

        return new SyncStatus(
            lastSyncedAt: $lastSyncedAt ? \Carbon\Carbon::parse($lastSyncedAt) : null,
            isConfigured: $isConfigured,
            isConnected: $isConnected,
        );
    }
}
