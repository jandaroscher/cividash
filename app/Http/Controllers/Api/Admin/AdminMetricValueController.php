<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMetricValueRequest;
use App\Http\Requests\Admin\UpdateMetricValueRequest;
use App\Models\MetricValue;
use Illuminate\Http\JsonResponse;

class AdminMetricValueController extends Controller
{
    /**
     * Create a new MetricValue scoped to the current tenant.
     *
     * The incoming request is validated; `tenant_id` is assigned automatically by the tenant-scoping trait.
     *
     * @param StoreMetricValueRequest $request Validated request data for the new metric value.
     * @return JsonResponse JSON with a `data` object containing `id`, `metric_definition_id`, `tile_year_id`, `value`, `tenant_id`, and `created_at` (ISO 8601). HTTP status 201.
     */
    public function store(StoreMetricValueRequest $request): JsonResponse
    {
        $metricValue = MetricValue::create($request->validated());

        return response()->json([
            'data' => [
                'id' => $metricValue->id,
                'metric_definition_id' => $metricValue->metric_definition_id,
                'tile_year_id' => $metricValue->tile_year_id,
                'value' => $metricValue->value,
                'is_active' => $metricValue->is_active,
                'tenant_id' => $metricValue->tenant_id,
                'created_at' => $metricValue->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update the specified MetricValue.
     *
     * The response contains the updated resource representation. The `tenant_id` field
     * is not modifiable via this endpoint.
     *
     * @param UpdateMetricValueRequest $request Validated input for updating the metric value.
     * @param int $id ID of the MetricValue to update.
     * @return \Illuminate\Http\JsonResponse JSON with a `data` object containing `id`, `metric_definition_id`,
     * `tile_year_id`, `value`, `tenant_id`, and `updated_at` (ISO 8601 string or null).
     */
    public function update(UpdateMetricValueRequest $request, int $id): JsonResponse
    {
        $metricValue = MetricValue::findOrFail($id);
        
        $metricValue->update($request->validated());

        return response()->json([
            'data' => [
                'id' => $metricValue->id,
                'metric_definition_id' => $metricValue->metric_definition_id,
                'tile_year_id' => $metricValue->tile_year_id,
                'value' => $metricValue->value,
                'is_active' => $metricValue->is_active,
                'tenant_id' => $metricValue->tenant_id,
                'updated_at' => $metricValue->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete the metric value identified by the given ID within the current tenant context.
     *
     * @param int $id The ID of the metric value to delete.
     * @return \Illuminate\Http\JsonResponse A JSON response with a null payload and HTTP status 204 No Content.
     */
    public function destroy(int $id): JsonResponse
    {
        $metricValue = MetricValue::findOrFail($id);
        $metricValue->delete();

        return response()->json(null, 204);
    }
}