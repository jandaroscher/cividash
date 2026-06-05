<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTimePeriodRequest;
use App\Http\Requests\Admin\UpdateTimePeriodRequest;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Services\TimePeriodService;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin API - Time Periods
 *
 * Endpoints for managing time periods (data points per period). Requires authentication with admin-api ability.
 */
class AdminTimePeriodController extends Controller
{
    public function __construct(
        private readonly TimePeriodService $timePeriodService
    ) {}

    /**
     * Create a new time period
     *
     * @authenticated
     *
     * @bodyParam tile_id integer required The ID of the parent tile. Example: 42
     * @bodyParam period_key string required The period identifier (e.g., "2024", "2024-Q1"). Example: 2024
     *
     * @response 201 scenario="Time period created" {"data": {"id": 10, "tile_id": 42, "granularity": "year", "period_key": "2024", "label": "2024", "tenant_id": 1, "created_at": "2026-04-15T10:00:00+00:00"}}
     */
    public function store(StoreTimePeriodRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tile = Tile::findOrFail($validated['tile_id']);

        $timePeriod = $this->timePeriodService->resolveOrCreate($validated['period_key'], $tile);

        return response()->json([
            'data' => [
                'id' => $timePeriod->id,
                'tile_id' => $timePeriod->tile_id,
                'granularity' => $timePeriod->granularity,
                'period_key' => $timePeriod->period_key,
                'label' => $timePeriod->label,
                'tenant_id' => $timePeriod->tenant_id,
                'created_at' => $timePeriod->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update a time period
     *
     * @authenticated
     *
     * @urlParam id integer required The ID of the time period. Example: 10
     *
     * @bodyParam period_key string The period identifier. Example: 2025
     * @bodyParam label string Custom label override. Example: FY 2025
     *
     * @response 200 scenario="Time period updated" {"data": {"id": 10, "tile_id": 42, "granularity": "year", "period_key": "2025", "label": "2025", "tenant_id": 1, "updated_at": "2026-04-15T10:30:00+00:00"}}
     */
    public function update(UpdateTimePeriodRequest $request, int $id): JsonResponse
    {
        $timePeriod = TimePeriod::findOrFail($id);
        $validated = $request->validated();

        // Regenerate label when period_key changes and no explicit label provided
        if (isset($validated['period_key']) && $validated['period_key'] !== $timePeriod->period_key && ! isset($validated['label'])) {
            $timePeriod->period_key = $validated['period_key'];
            $validated['label'] = $this->timePeriodService->regenerateLabel($timePeriod);
        }

        $timePeriod->update($validated);

        return response()->json([
            'data' => [
                'id' => $timePeriod->id,
                'tile_id' => $timePeriod->tile_id,
                'granularity' => $timePeriod->granularity,
                'period_key' => $timePeriod->period_key,
                'label' => $timePeriod->label,
                'tenant_id' => $timePeriod->tenant_id,
                'updated_at' => $timePeriod->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete a time period
     *
     * @authenticated
     *
     * @urlParam id integer required The ID of the time period to delete. Example: 10
     *
     * @response 204 scenario="Time period deleted"
     */
    public function destroy(int $id): JsonResponse
    {
        $timePeriod = TimePeriod::findOrFail($id);
        $timePeriod->delete();

        return response()->json(null, 204);
    }
}
