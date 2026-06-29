<?php

namespace App\Contracts\Integration;

use App\Models\Tenant;
use App\Services\Integration\SyncResult;
use App\Services\Integration\SyncStatus;

interface SyncServiceInterface
{
    /**
     * Synchronise all entities from the external source for the given tenant.
     *
     * @param  Tenant  $tenant  Tenant to sync data into.
     * @param  bool  $force  When true, ignore last_synced_at and re-import everything.
     * @param  bool  $dryRun  When true, compute changes without persisting them.
     */
    public function syncAll(Tenant $tenant, bool $force = false, bool $dryRun = false): SyncResult;

    /**
     * Synchronise a single entity identified by its external ID.
     */
    public function syncEntity(Tenant $tenant, string $externalId): SyncResult;

    /**
     * Return a status summary of the most recent sync for the given tenant.
     */
    public function getLastSyncStatus(Tenant $tenant): SyncStatus;
}
