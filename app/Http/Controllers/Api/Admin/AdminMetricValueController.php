<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMetricValueRequest;
use App\Http\Requests\Admin\UpdateMetricValueRequest;
use App\Models\MetricValue;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin API - Metric Values
 *
 * Endpoints for managing metric values (actual KPI data). Requires authentication with admin-api ability.
 */
class AdminMetricValueController extends Controller
{
    /**
     * Create a new metric value
     *
     * Stores the actual value for a metric definition in a specific time period.
     * The tenant_id is automatically set from the token's tenant context.
     *
     * @authenticated
     *
     * @bodyParam metric_definition_id integer required The ID of the metric definition. Example: 5
     * @bodyParam time_period_id integer required The ID of the time period. Example: 10
     * @bodyParam value numeric required The actual metric value. Example: 150000
     * @bodyParam is_active boolean Whether the value is active. Example: true
     *
     * @response 201 scenario="Metric value created" {"data": {"id": 100, "metric_definition_id": 5, "time_period_id": 10, "value": 150000, "is_active": true, "tenant_id": 1, "created_at": "2025-01-22T10:00:00+00:00"}}
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 422 scenario="Validation error" {"message": "The value field is required.", "errors": {"value": ["The value field is required."]}}
     */
    public function store(StoreMetricValueRequest $request): JsonResponse
    {
        $metricValue = MetricValue::create($request->validated());

        return response()->json([
            'data' => [
                'id' => $metricValue->id,
                'metric_definition_id' => $metricValue->metric_definition_id,
                'time_period_id' => $metricValue->time_period_id,
                'value' => $metricValue->value,
                'is_active' => $metricValue->is_active,
                'tenant_id' => $metricValue->tenant_id,
                'created_at' => $metricValue->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update a metric value
     *
     * Updates an existing metric value. Only metric values belonging to the authenticated
     * user's tenant can be updated. The tenant_id cannot be changed.
     *
     * @authenticated
     *
     * @urlParam id integer required The ID of the metric value. Example: 100
     *
     * @bodyParam metric_definition_id integer The ID of the metric definition. Example: 5
     * @bodyParam time_period_id integer The ID of the time period. Example: 10
     * @bodyParam value numeric The actual metric value. Example: 155000
     * @bodyParam is_active boolean Whether the value is active.
     *
     * @response 200 scenario="Metric value updated" {"data": {"id": 100, "metric_definition_id": 5, "time_period_id": 10, "value": 155000, "is_active": true, "tenant_id": 1, "updated_at": "2025-01-22T10:30:00+00:00"}}
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 404 scenario="Metric value not found" {"message": "No query results for model [App\\Models\\MetricValue] 999"}
     */
    public function update(UpdateMetricValueRequest $request, int $id): JsonResponse
    {
        $metricValue = MetricValue::findOrFail($id);

        $metricValue->update($request->validated());

        return response()->json([
            'data' => [
                'id' => $metricValue->id,
                'metric_definition_id' => $metricValue->metric_definition_id,
                'time_period_id' => $metricValue->time_period_id,
                'value' => $metricValue->value,
                'is_active' => $metricValue->is_active,
                'tenant_id' => $metricValue->tenant_id,
                'updated_at' => $metricValue->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete a metric value
     *
     * Permanently deletes a metric value. Only metric values belonging to the authenticated
     * user's tenant can be deleted.
     *
     * @authenticated
     *
     * @urlParam id integer required The ID of the metric value to delete. Example: 100
     *
     * @response 204 scenario="Metric value deleted"
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 404 scenario="Metric value not found" {"message": "No query results for model [App\\Models\\MetricValue] 999"}
     */
    public function destroy(int $id): JsonResponse
    {
        $metricValue = MetricValue::findOrFail($id);
        $metricValue->delete();

        return response()->json(null, 204);
    }
}
