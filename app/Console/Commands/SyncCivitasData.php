<?php

namespace App\Console\Commands;

use App\Contracts\Integration\SyncServiceInterface;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SyncCivitasData extends Command
{
    protected $signature = 'integration:sync-civitas
                            {--tenant= : Tenant slug to sync (syncs all tenants if omitted)}
                            {--force : Ignore last_synced_at and re-import everything}
                            {--dry-run : Compute changes without persisting them}';

    protected $description = 'Synchronise data from CIVITAS/CORE into the dashboard via NGSI-LD API';

    public function handle(SyncServiceInterface $syncService): int
    {
        if (! config('integrations.civitas.enabled')) {
            $this->warn('CIVITAS integration is disabled. Set CIVITAS_ENABLED=true to enable.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $tenantSlug = $this->option('tenant');

        $tenants = $tenantSlug
            ? Tenant::where('slug', $tenantSlug)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error("No tenant found" . ($tenantSlug ? " with slug '{$tenantSlug}'" : '') . '.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->info("Syncing tenant: {$tenant->name} ({$tenant->slug})");

            $result = $syncService->syncAll($tenant, $force, $dryRun);

            $this->table(
                ['Created', 'Updated', 'Deleted', 'Skipped', 'Failed', 'Dry Run'],
                [[$result->created, $result->updated, $result->deleted, $result->skipped, $result->failed, $result->dryRun ? 'Yes' : 'No']],
            );

            if ($result->hasErrors()) {
                foreach ($result->errors as $error) {
                    $this->error("  - {$error}");
                }
            }
        }

        return self::SUCCESS;
    }
}
