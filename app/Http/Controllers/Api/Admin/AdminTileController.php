<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTileRequest;
use App\Http\Requests\Admin\UpdateTileRequest;
use App\Models\Tile;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin API - Tiles
 *
 * Endpoints for managing tiles (dashboard cards). Requires authentication with admin-api ability.
 */
class AdminTileController extends Controller
{
    /**
     * Create a new tile
     *
     * Creates a new tile for the authenticated user's tenant. The tenant_id is automatically
     * set from the token's tenant context and cannot be overridden via the request payload.
     *
     * @authenticated
     *
     * @bodyParam title object required Translatable title. Example: {"de": "Energie", "en": "Energy"}
     * @bodyParam description object Translatable description. Example: {"de": "Beschreibung", "en": "Description"}
     * @bodyParam icon string Icon identifier. Example: chart-bar
     * @bodyParam position integer Display position/order. Example: 1
     * @bodyParam is_public boolean Whether tile is publicly visible. Example: true
     * @bodyParam meta_title object Translatable SEO title. Example: {"de": "SEO Titel", "en": "SEO Title"}
     * @bodyParam meta_description object Translatable SEO description.
     * @bodyParam meta_image string URL to meta image.
     *
     * @response 201 scenario="Tile created" {"data": {"id": 42, "title": {"de": "Energie", "en": "Energy"}, "slug": {"de": "energie", "en": "energy"}, "description": {"de": "Beschreibung", "en": "Description"}, "icon": "chart-bar", "position": 1, "is_public": true, "meta_title": null, "meta_description": null, "meta_image": null, "tenant_id": 1, "created_at": "2025-01-22T10:00:00+00:00"}}
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 422 scenario="Validation error" {"message": "The title field is required.", "errors": {"title": ["The title field is required."]}}
     */
    public function store(StoreTileRequest $request): JsonResponse
    {
        $tile = Tile::create($request->validated());

        return response()->json([
            'data' => [
                'id' => $tile->id,
                'title' => $tile->getTranslations('title'),
                'slug' => $tile->getTranslations('slug'),
                'description' => $tile->getTranslations('description'),
                'icon' => $tile->icon,
                'position' => $tile->position,
                'is_public' => $tile->is_public,
                'meta_title' => $tile->getTranslations('meta_title'),
                'meta_description' => $tile->getTranslations('meta_description'),
                'meta_image' => $tile->meta_image,
                'tenant_id' => $tile->tenant_id,
                'created_at' => $tile->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update a tile
     *
     * Updates an existing tile. Only tiles belonging to the authenticated user's tenant can be updated.
     * The tenant_id field cannot be changed via this endpoint. Attempting to access a tile from
     * another tenant returns 404 (not 403) to prevent data leakage.
     *
     * @authenticated
     *
     * @urlParam id integer required The ID of the tile. Example: 42
     *
     * @bodyParam title object Translatable title. Example: {"de": "Aktualisierter Titel"}
     * @bodyParam description object Translatable description.
     * @bodyParam icon string Icon identifier.
     * @bodyParam position integer Display position/order.
     * @bodyParam is_public boolean Whether tile is publicly visible.
     * @bodyParam meta_title object Translatable SEO title.
     * @bodyParam meta_description object Translatable SEO description.
     * @bodyParam meta_image string URL to meta image.
     *
     * @response 200 scenario="Tile updated" {"data": {"id": 42, "title": {"de": "Aktualisierter Titel", "en": "Energy"}, "slug": {"de": "energie", "en": "energy"}, "description": {"de": "Beschreibung", "en": "Description"}, "icon": "chart-bar", "position": 1, "is_public": true, "meta_title": null, "meta_description": null, "meta_image": null, "tenant_id": 1, "updated_at": "2025-01-22T10:30:00+00:00"}}
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 404 scenario="Tile not found" {"message": "No query results for model [App\\Models\\Tile] 999"}
     * @response 422 scenario="Validation error" {"message": "The given data was invalid.", "errors": {"title": ["The title must be an array."]}}
     */
    public function update(UpdateTileRequest $request, int $id): JsonResponse
    {
        // Global scope ensures only tiles from current tenant are found
        $tile = Tile::findOrFail($id);

        $tile->update($request->validated());

        return response()->json([
            'data' => [
                'id' => $tile->id,
                'title' => $tile->getTranslations('title'),
                'slug' => $tile->getTranslations('slug'),
                'description' => $tile->getTranslations('description'),
                'icon' => $tile->icon,
                'position' => $tile->position,
                'is_public' => $tile->is_public,
                'meta_title' => $tile->getTranslations('meta_title'),
                'meta_description' => $tile->getTranslations('meta_description'),
                'meta_image' => $tile->meta_image,
                'tenant_id' => $tile->tenant_id,
                'updated_at' => $tile->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete a tile
     *
     * Permanently deletes a tile. Only tiles belonging to the authenticated user's tenant can be deleted.
     * Attempting to delete a tile from another tenant returns 404 (not 403) to prevent data leakage.
     *
     * @authenticated
     *
     * @urlParam id integer required The ID of the tile to delete. Example: 42
     *
     * @response 204 scenario="Tile deleted"
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 404 scenario="Tile not found" {"message": "No query results for model [App\\Models\\Tile] 999"}
     */
    public function destroy(int $id): JsonResponse
    {
        $tile = Tile::findOrFail($id);
        $tile->delete();

        return response()->json(null, 204);
    }
}
