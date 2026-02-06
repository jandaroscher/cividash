<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTileYearRequest;
use App\Http\Requests\Admin\UpdateTileYearRequest;
use App\Models\TileYear;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin API - Tile Years
 *
 * Endpoints for managing tile years (data points per year). Requires authentication with admin-api ability.
 */
class AdminTileYearController extends Controller
{
    /**
     * Create a new tile year
     *
     * Associates a year with a tile for storing year-specific metric values.
     * The tenant_id is automatically set from the token's tenant context.
     *
     * @authenticated
     *
     * @bodyParam tile_id integer required The ID of the parent tile. Example: 42
     * @bodyParam year integer required The year (e.g., 2024). Example: 2024
     *
     * @response 201 scenario="Tile year created" {"data": {"id": 10, "tile_id": 42, "year": 2024, "tenant_id": 1, "created_at": "2025-01-22T10:00:00+00:00"}}
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 422 scenario="Validation error" {"message": "The tile id field is required.", "errors": {"tile_id": ["The tile id field is required."]}}
     */
    public function store(StoreTileYearRequest $request): JsonResponse
    {
        $tileYear = TileYear::create($request->validated());

        return response()->json([
            'data' => [
                'id' => $tileYear->id,
                'tile_id' => $tileYear->tile_id,
                'year' => $tileYear->year,
                'tenant_id' => $tileYear->tenant_id,
                'created_at' => $tileYear->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update a tile year
     *
     * Updates an existing tile year. Only tile years belonging to the authenticated user's tenant
     * can be updated. The tenant_id cannot be changed.
     *
     * @authenticated
     *
     * @urlParam id integer required The ID of the tile year. Example: 10
     *
     * @bodyParam tile_id integer The ID of the parent tile. Example: 42
     * @bodyParam year integer The year. Example: 2025
     *
     * @response 200 scenario="Tile year updated" {"data": {"id": 10, "tile_id": 42, "year": 2025, "tenant_id": 1, "updated_at": "2025-01-22T10:30:00+00:00"}}
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 404 scenario="Tile year not found" {"message": "No query results for model [App\\Models\\TileYear] 999"}
     * @response 422 scenario="Validation error" {"message": "The year must be an integer.", "errors": {"year": ["The year must be an integer."]}}
     */
    public function update(UpdateTileYearRequest $request, int $id): JsonResponse
    {
        $tileYear = TileYear::findOrFail($id);

        $tileYear->update($request->validated());

        return response()->json([
            'data' => [
                'id' => $tileYear->id,
                'tile_id' => $tileYear->tile_id,
                'year' => $tileYear->year,
                'tenant_id' => $tileYear->tenant_id,
                'updated_at' => $tileYear->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete a tile year
     *
     * Permanently deletes a tile year. Only tile years belonging to the authenticated user's tenant
     * can be deleted. Attempting to delete a tile year from another tenant returns 404.
     *
     * @authenticated
     *
     * @urlParam id integer required The ID of the tile year to delete. Example: 10
     *
     * @response 204 scenario="Tile year deleted"
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 404 scenario="Tile year not found" {"message": "No query results for model [App\\Models\\TileYear] 999"}
     */
    public function destroy(int $id): JsonResponse
    {
        $tileYear = TileYear::findOrFail($id);
        $tileYear->delete();

        return response()->json(null, 204);
    }
}
