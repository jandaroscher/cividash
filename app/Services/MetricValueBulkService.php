<?php

namespace App\Services;

use App\Models\MetricValue;
use App\Models\Tile;

class MetricValueBulkService
{
    public function __construct(
        private readonly TimePeriodService $timePeriodService,
    ) {}

    /**
     * Persist a flat form state to the database in a single transaction.
     *
     * State keys follow the pattern "tile_{tileId}.metric_{defId}".
     * Empty / null values are skipped (no create, no delete).
     *
     * Tiles are pre-filtered to match the selected granularity, so
     * $tile->time_granularity always equals $granularity — TimePeriodService
     * resolveOrCreate() is therefore safe to call directly (it uses the
     * tile's own granularity internally, which is identical here).
     *
     * @param  array<string, mixed>  $state  Flat form state keyed by "tile_{id}.metric_{defId}"
     * @param  string  $periodKey  ISO period key (e.g. "2024", "2024-Q1")
     * @param  \Illuminate\Database\Eloquent\Collection<int, Tile>  $tiles  Eager-loaded tiles with metricDefinitions
     */
    public function save(array $state, string $periodKey, $tiles): void
    {
        DB::transaction(function () use ($state, $periodKey, $tiles): void {
            // Cache resolved TimePeriods per tile_id to avoid redundant queries
            $timePeriodByTile = [];

            foreach ($tiles as $tile) {
                $tileKey = "tile_{$tile->id}";

                if (! isset($state[$tileKey])) {
                    continue;
                }

                $tileState = $state[$tileKey];

                // Check if any non-empty value exists for this tile before creating a TimePeriod
                $hasValue = collect($tileState)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();

                if (! $hasValue) {
                    continue;
                }

                // Delegate to TimePeriodService — handles normalizeInput + firstOrCreate
                if (! isset($timePeriodByTile[$tile->id])) {
                    $timePeriodByTile[$tile->id] = $this->timePeriodService->resolveOrCreate($periodKey, $tile);
                }

                $timePeriod = $timePeriodByTile[$tile->id];

                foreach ($tile->metricDefinitions as $def) {
                    // Integrity guard: definition must belong to this tile
                    if ((int) $def->tile_id !== (int) $tile->id) {
                        continue;
                    }

                    $rawValue = $tileState["metric_{$def->id}"] ?? null;

                    // Skip empty values — no create, no delete (explicit decision)
                    if ($rawValue === null || $rawValue === '') {
                        continue;
                    }

                    MetricValue::updateOrCreate(
                        [
                            'metric_definition_id' => $def->id,
                            'time_period_id' => $timePeriod->id,
                        ],
                        [
                            'value' => $rawValue,
                            'tenant_id' => $tile->tenant_id,
                        ]
                    );
                }
            }
        });
    }
}
