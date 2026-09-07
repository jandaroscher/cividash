<?php

namespace App\Services\Integration;

use App\Contracts\Integration\DataMapperInterface;
use App\Contracts\Integration\ExternalDataSourceInterface;
use App\Contracts\Integration\SyncServiceInterface;
use App\Enums\TimeGranularity;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Settings\IntegrationSettings;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates synchronisation between an external data source and the dashboard.
 *
 * Uses last_synced_at and source_hash on models to perform incremental updates.
 * Entities that have not changed since the last sync are skipped.
 */
class SyncService implements SyncServiceInterface
{
    /**
     * Provenance marker written to external_source on every synced record.
     */
    public const SOURCE_KEY = 'civitas-core';

    /**
     * NGSI-LD entity type pulled from the external source.
     */
    public const ENTITY_TYPE = 'NachhaltigkeitsIndikator';

    public function __construct(
        private readonly ExternalDataSourceInterface $source,
        private readonly DataMapperInterface $mapper,
    ) {}

    public function syncAll(Tenant $tenant, bool $force = false, bool $dryRun = false): SyncResult
    {
        // isQuiet: true skips the TenantSet event, which requires an authenticated
        // user — there is none in a scheduled/console run. We scope every write with
        // an explicit tenant_id anyway, so the quiet context is sufficient.
        Filament::setTenant($tenant, isQuiet: true);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $deleted = 0;
        $errors = [];
        $seenExternalIds = [];

        $batchSize = $this->batchSize();
        $offset = 0;
        $total = null;

        do {
            $page = $this->source->fetchEntities(self::ENTITY_TYPE, [], $batchSize, $offset);
            $entities = $page['entities'] ?? [];
            $total ??= (int) ($page['total'] ?? 0);

            foreach ($entities as $entity) {
                try {
                    if (! is_array($entity) || empty($entity['id'])) {
                        throw new \RuntimeException('External entity is missing an id.');
                    }

                    $externalId = (string) $entity['id'];
                    $seenExternalIds[] = $externalId;

                    $outcome = $this->syncSingle($tenant, $entity, $force, $dryRun);

                    match ($outcome) {
                        'created' => $created++,
                        'updated' => $updated++,
                        default => $skipped++,
                    };
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = $e->getMessage();
                    Log::warning('CIVITAS sync: failed to sync entity.', [
                        'tenant' => $tenant->slug,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $offset += $batchSize;
        } while ($offset < $total && ! empty($entities));

        if (config('integrations.civitas.sync.prune_removed') && ! $dryRun) {
            $deleted = $this->pruneRemoved($tenant, $seenExternalIds);
        }

        return new SyncResult(
            created: $created,
            updated: $updated,
            deleted: $deleted,
            skipped: $skipped,
            failed: $failed,
            errors: $errors,
            dryRun: $dryRun,
        );
    }

    public function syncEntity(Tenant $tenant, string $externalId): SyncResult
    {
        // isQuiet: true skips the TenantSet event, which requires an authenticated
        // user — there is none in a scheduled/console run. We scope every write with
        // an explicit tenant_id anyway, so the quiet context is sufficient.
        Filament::setTenant($tenant, isQuiet: true);

        $entity = $this->source->fetchEntity($externalId);

        if ($entity === null) {
            return new SyncResult(failed: 1, errors: ["Entity {$externalId} not found in external source."]);
        }

        try {
            $outcome = $this->syncSingle($tenant, $entity, force: true, dryRun: false);
        } catch (\Throwable $e) {
            return new SyncResult(failed: 1, errors: [$e->getMessage()]);
        }

        return new SyncResult(
            created: $outcome === 'created' ? 1 : 0,
            updated: $outcome === 'updated' ? 1 : 0,
            skipped: $outcome === 'skipped' ? 1 : 0,
        );
    }

    /**
     * Sync a single external entity into the dashboard data model.
     *
     * @return 'created'|'updated'|'skipped'
     */
    private function syncSingle(Tenant $tenant, array $entity, bool $force, bool $dryRun): string
    {
        $externalId = (string) $entity['id'];
        $hash = $this->mapper->computeSourceHash($entity);

        $existing = Tile::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('external_source', self::SOURCE_KEY)
            ->where('external_id', $externalId)
            ->first();

        $isNew = $existing === null;

        if (! $isNew && ! $force && $existing->source_hash === $hash) {
            return 'skipped';
        }

        if ($dryRun) {
            return $isNew ? 'created' : 'updated';
        }

        DB::transaction(function () use ($tenant, $entity, $existing, $hash, $externalId) {
            $tileAttrs = $this->mapper->mapToTile($entity);
            $tile = $existing ?? new Tile;
            $tile->fill($this->fillableOnly($tile, $tileAttrs));
            $tile->tenant_id = $tenant->id;
            $tile->external_source = self::SOURCE_KEY;
            $tile->external_id = $externalId;
            $tile->source_hash = $hash;
            $tile->last_synced_at = now();
            $tile->save();

            $definitionAttrs = $this->mapper->mapToMetricDefinition($entity);
            $metricKey = $definitionAttrs['metric_key'] ?? $externalId;

            $definition = MetricDefinition::withoutGlobalScopes()
                ->where('tile_id', $tile->id)
                ->where('metric_key', $metricKey)
                ->first() ?? new MetricDefinition;

            $definition->fill($this->fillableOnly($definition, $definitionAttrs));
            $definition->tile_id = $tile->id;
            $definition->tenant_id = $tenant->id;
            $definition->external_source = self::SOURCE_KEY;
            $definition->external_id = $definitionAttrs['external_id'] ?? $externalId;
            $definition->source_hash = $hash;
            $definition->last_synced_at = now();
            $definition->save();

            $granularity = TimeGranularity::tryFrom($tileAttrs['time_granularity'] ?? 'year') ?? TimeGranularity::Year;

            foreach ($this->mapper->mapToMetricValues($entity) as $pair) {
                $timePeriod = TimePeriod::withoutGlobalScopes()
                    ->where('tile_id', $tile->id)
                    ->where('period_key', $pair['period'])
                    ->first();

                if ($timePeriod === null) {
                    $timePeriod = new TimePeriod;
                    $timePeriod->tile_id = $tile->id;
                    $timePeriod->granularity = $granularity;
                    $timePeriod->period_key = $pair['period'];
                    $timePeriod->label = $granularity->generateLabel($pair['period']);
                    $timePeriod->tenant_id = $tenant->id;
                    $timePeriod->save();
                }

                $metricValue = MetricValue::withoutGlobalScopes()
                    ->where('metric_definition_id', $definition->id)
                    ->where('time_period_id', $timePeriod->id)
                    ->first() ?? new MetricValue;

                $metricValue->fill($this->fillableOnly($metricValue, $this->mapper->mapToMetricValue($pair)));
                $metricValue->metric_definition_id = $definition->id;
                $metricValue->time_period_id = $timePeriod->id;
                $metricValue->tenant_id = $tenant->id;
                $metricValue->save();
            }

            $categoryAttrs = $this->mapper->mapToCategory($entity);

            if ($categoryAttrs !== []) {
                $category = Category::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('external_source', self::SOURCE_KEY)
                    ->where('external_id', $categoryAttrs['external_id'])
                    ->first() ?? new Category;

                $category->fill($this->fillableOnly($category, $categoryAttrs));
                $category->tenant_id = $tenant->id;
                $category->external_source = self::SOURCE_KEY;
                $category->external_id = $categoryAttrs['external_id'];
                $category->last_synced_at = now();
                $category->save();

                $tile->categories()->syncWithoutDetaching([$category->id]);
            }
        });

        return $isNew ? 'created' : 'updated';
    }

    /**
     * Delete tiles previously synced from this source whose external_id no longer
     * appears upstream. Child rows cascade via foreign keys.
     */
    private function pruneRemoved(Tenant $tenant, array $seenExternalIds): int
    {
        // Guard: an empty seen-list makes whereNotIn() match every row (WHERE 1=1)
        // and would delete ALL synced tiles — e.g. when the source returns nothing
        // due to a transient or auth error. Never prune when we saw no entities.
        if ($seenExternalIds === []) {
            return 0;
        }

        $stale = Tile::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('external_source', self::SOURCE_KEY)
            ->whereNotIn('external_id', $seenExternalIds)
            ->get();

        $count = 0;

        foreach ($stale as $tile) {
            $tile->delete();
            $count++;
        }

        return $count;
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
            lastSyncedAt: $lastSyncedAt ? Carbon::parse($lastSyncedAt) : null,
            isConfigured: $isConfigured,
            isConnected: $isConnected,
        );
    }

    /**
     * Resolve the page size for paginated fetches.
     */
    private function batchSize(): int
    {
        $settings = rescue(fn () => app(IntegrationSettings::class), null, false);

        return (int) ($settings?->sync_batch_size
            ?? config('integrations.civitas.sync.batch_size', 100));
    }

    /**
     * Filter a mapped attribute array down to the model's fillable columns so
     * that guarded provenance columns are set explicitly rather than mass-assigned.
     *
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     */
    private function fillableOnly(Model $model, array $attributes): array
    {
        $fillable = $model->getFillable();

        return array_intersect_key($attributes, array_flip($fillable));
    }
}
