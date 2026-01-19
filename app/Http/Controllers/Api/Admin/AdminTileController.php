<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTileRequest;
use App\Http\Requests\Admin\UpdateTileRequest;
use App\Models\Tile;
use Illuminate\Http\JsonResponse;

class AdminTileController extends Controller
{
    /**
     * Create a new Tile from the request's validated data and return its representation.
     *
     * Tenant association is applied automatically (e.g., via the BelongsToTenant trait).
     *
     * @param StoreTileRequest $request The validated request containing Tile attributes.
     * @return JsonResponse JSON response with HTTP 201 and a `data` object containing the created Tile's `id`, `title`, `description`, `icon`, `position`, `tenant_id`, and `created_at` (ISO 8601 string or null).
     */
    public function store(StoreTileRequest $request): JsonResponse
    {
        $tile = Tile::create($request->validated());

        return response()->json([
            'data' => [
                'id' => $tile->id,
                'title' => $tile->title,
                'description' => $tile->description,
                'icon' => $tile->icon,
                'position' => $tile->position,
                'tenant_id' => $tile->tenant_id,
                'created_at' => $tile->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update an existing Tile within the current tenant and return its updated representation.
     *
     * The tile's tenant_id cannot be changed via this endpoint; only tiles scoped to the current tenant are accessible.
     *
     * @param UpdateTileRequest $request Validated update data for the tile.
     * @param int $id The ID of the tile to update.
     * @return JsonResponse JSON body with a `data` object containing the tile's id, title, description, icon, position, tenant_id, and updated_at (ISO 8601) fields.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the tile does not exist within the current tenant scope.
     */
    public function update(UpdateTileRequest $request, int $id): JsonResponse
    {
        // Global scope ensures only tiles from current tenant are found
        $tile = Tile::findOrFail($id);
        
        $tile->update($request->validated());

        return response()->json([
            'data' => [
                'id' => $tile->id,
                'title' => $tile->title,
                'description' => $tile->description,
                'icon' => $tile->icon,
                'position' => $tile->position,
                'tenant_id' => $tile->tenant_id,
                'updated_at' => $tile->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete the specified tile belonging to the current tenant.
     *
     * If the tile is not found in the current tenant context, a 404 response is produced.
     *
     * @param int $id The ID of the tile to delete.
     * @return \Illuminate\Http\JsonResponse JSON response with a null body and HTTP 204 No Content.
     */
    public function destroy(int $id): JsonResponse
    {
        $tile = Tile::findOrFail($id);
        $tile->delete();

        return response()->json(null, 204);
    }
}