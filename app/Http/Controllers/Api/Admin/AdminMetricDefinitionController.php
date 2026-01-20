<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMetricDefinitionRequest;
use App\Http\Requests\Admin\UpdateMetricDefinitionRequest;
use App\Models\MetricDefinition;
use Illuminate\Http\JsonResponse;

class AdminMetricDefinitionController extends Controller
{
    /**
     * Create a new MetricDefinition from validated request data.
     *
     * The created resource's `tenant_id` is set automatically via the BelongsToTenant trait.
     *
     * @param StoreMetricDefinitionRequest $request The validated input for the new metric definition.
     * @return JsonResponse JSON response with HTTP 201 containing a `data` object with the created resource:
     *                      `id`, `tile_id`, `metric_key`, `label`, `unit`, `icon`, `indicator_type`,
     *                      `tenant_id`, and `created_at` (ISO 8601 string or null).
     */
    public function store(StoreMetricDefinitionRequest $request): JsonResponse
    {
        $definition = MetricDefinition::create($request->validated());

        return response()->json([
            'data' => [
                'id' => $definition->id,
                'tile_id' => $definition->tile_id,
                'metric_key' => $definition->metric_key,
                'label' => $definition->label,
                'unit' => $definition->unit,
                'icon' => $definition->icon,
                'indicator_type' => $definition->indicator_type,
                'is_active' => $definition->is_active,
                'tenant_id' => $definition->tenant_id,
                'created_at' => $definition->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update the specified metric definition using validated request data; `tenant_id` is not changed.
     *
     * @param UpdateMetricDefinitionRequest $request Validated input for the metric definition's updatable fields.
     * @param int $id ID of the MetricDefinition to update.
     * @return \Illuminate\Http\JsonResponse JSON object with a `data` key containing the updated metric definition fields: `id`, `tile_id`, `metric_key`, `label`, `unit`, `icon`, `indicator_type`, `tenant_id`, and `updated_at` (ISO 8601 string or null).
     */
    public function update(UpdateMetricDefinitionRequest $request, int $id): JsonResponse
    {
        $definition = MetricDefinition::findOrFail($id);
        
        $definition->update($request->validated());

        return response()->json([
            'data' => [
                'id' => $definition->id,
                'tile_id' => $definition->tile_id,
                'metric_key' => $definition->metric_key,
                'label' => $definition->label,
                'unit' => $definition->unit,
                'icon' => $definition->icon,
                'indicator_type' => $definition->indicator_type,
                'is_active' => $definition->is_active,
                'tenant_id' => $definition->tenant_id,
                'updated_at' => $definition->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete a metric definition by its ID.
     *
     * @param int $id The ID of the metric definition to delete.
     * @return \Illuminate\Http\JsonResponse Empty response with HTTP 204 No Content on success.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If no metric definition exists for the given ID.
     */
    public function destroy(int $id): JsonResponse
    {
        $definition = MetricDefinition::findOrFail($id);
        $definition->delete();

        return response()->json(null, 204);
    }
}