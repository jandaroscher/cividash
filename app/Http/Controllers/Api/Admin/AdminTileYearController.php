<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTileYearRequest;
use App\Http\Requests\Admin\UpdateTileYearRequest;
use App\Models\TileYear;
use Illuminate\Http\JsonResponse;

class AdminTileYearController extends Controller
{
    /**
     * Create a new TileYear and return its representation.
     *
     * The created resource includes `id`, `tile_id`, `year`, `tenant_id`, and `created_at` (ISO 8601).
     * The `tenant_id` is populated automatically from the tenant context.
     *
     * @param StoreTileYearRequest $request Validated input for the new TileYear.
     * @return JsonResponse JSON payload with a `data` object describing the created TileYear; HTTP status 201.
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
         * Update the specified TileYear resource.
         *
         * tenant_id cannot be changed via this endpoint.
         *
         * @param UpdateTileYearRequest $request Validated request data for the update.
         * @param int $id The identifier of the TileYear to update.
         * @return \Illuminate\Http\JsonResponse JSON object with a `data` key containing `id`, `tile_id`, `year`, `tenant_id`, and `updated_at` (ISO 8601 string when present).
         * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If no TileYear exists with the given id.
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
     * Delete the specified tile year.
     *
     * Only tile years within the current tenant context can be deleted.
     *
     * @param int $id The identifier of the TileYear to delete.
     * @return \Illuminate\Http\JsonResponse A JSON response with HTTP status 204 (No Content) and a null body.
     */
    public function destroy(int $id): JsonResponse
    {
        $tileYear = TileYear::findOrFail($id);
        $tileYear->delete();

        return response()->json(null, 204);
    }
}