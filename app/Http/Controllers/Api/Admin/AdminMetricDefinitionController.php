<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMetricDefinitionRequest;
use App\Http\Requests\Admin\UpdateMetricDefinitionRequest;
use App\Models\MetricDefinition;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin API - Metric Definitions
 *
 * Endpoints for managing metric definitions (KPI structure). Requires authentication with admin-api ability.
 */
class AdminMetricDefinitionController extends Controller
{
    /**
     * Create a new metric definition
     *
     * Defines a new metric/KPI for a tile. The tenant_id is automatically set from
     * the token's tenant context.
     *
     * @authenticated
     *
     * @bodyParam tile_id integer required The ID of the parent tile. Example: 42
     * @bodyParam metric_key string required Unique key for the metric. Example: population
     * @bodyParam label object Translatable label. Example: {"de": "Einwohnerzahl", "en": "Population"}
     * @bodyParam unit object Translatable unit. Example: {"de": "Personen", "en": "People"}
     * @bodyParam icon string Icon identifier. Example: users
     * @bodyParam indicator_type string Type of indicator (e.g., number, percentage). Example: number
     * @bodyParam is_active boolean Whether the metric is active. Example: true
     *
     * @response 201 scenario="Metric definition created" {"data": {"id": 5, "tile_id": 42, "metric_key": "population", "label": {"de": "Einwohnerzahl", "en": "Population"}, "unit": {"de": "Personen", "en": "People"}, "icon": "users", "indicator_type": "number", "is_active": true, "tenant_id": 1, "created_at": "2025-01-22T10:00:00+00:00"}}
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 422 scenario="Validation error" {"message": "The metric key field is required.", "errors": {"metric_key": ["The metric key field is required."]}}
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
     * Update a metric definition
     *
     * Updates an existing metric definition. Only metric definitions belonging to the authenticated
     * user's tenant can be updated. The tenant_id cannot be changed.
     *
     * @authenticated
     *
     * @urlParam id integer required The ID of the metric definition. Example: 5
     *
     * @bodyParam tile_id integer The ID of the parent tile. Example: 42
     * @bodyParam metric_key string Unique key for the metric. Example: population
     * @bodyParam label object Translatable label. Example: {"de": "Bevölkerung"}
     * @bodyParam unit object Translatable unit.
     * @bodyParam icon string Icon identifier.
     * @bodyParam indicator_type string Type of indicator.
     * @bodyParam is_active boolean Whether the metric is active.
     *
     * @response 200 scenario="Metric definition updated" {"data": {"id": 5, "tile_id": 42, "metric_key": "population", "label": {"de": "Bevölkerung", "en": "Population"}, "unit": {"de": "Personen", "en": "People"}, "icon": "users", "indicator_type": "number", "is_active": true, "tenant_id": 1, "updated_at": "2025-01-22T10:30:00+00:00"}}
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 404 scenario="Metric definition not found" {"message": "No query results for model [App\\Models\\MetricDefinition] 999"}
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
     * Delete a metric definition
     *
     * Permanently deletes a metric definition. Only metric definitions belonging to the authenticated
     * user's tenant can be deleted. This may also delete associated metric values.
     *
     * @authenticated
     *
     * @urlParam id integer required The ID of the metric definition to delete. Example: 5
     *
     * @response 204 scenario="Metric definition deleted"
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 404 scenario="Metric definition not found" {"message": "No query results for model [App\\Models\\MetricDefinition] 999"}
     */
    public function destroy(int $id): JsonResponse
    {
        $definition = MetricDefinition::findOrFail($id);
        $definition->delete();

        return response()->json(null, 204);
    }
}
