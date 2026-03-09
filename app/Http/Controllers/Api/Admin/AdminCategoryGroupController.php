<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryGroupRequest;
use App\Http\Requests\Admin\UpdateCategoryGroupRequest;
use App\Models\CategoryGroup;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin API - Category Groups
 *
 * Endpoints for managing category groups. Requires authentication with admin-api ability.
 */
class AdminCategoryGroupController extends Controller
{
    /**
     * Create a new category group
     *
     * Creates a new category group for the authenticated user's tenant. The tenant_id is automatically
     * set from the token's tenant context and cannot be overridden via the request payload.
     *
     * @authenticated
     *
     * @bodyParam key string required Unique key identifier. Example: sdg-goals
     * @bodyParam title object required Translatable title. Example: {"de": "SDG-Ziele", "en": "SDG Goals"}
     * @bodyParam position integer Display position/order. Example: 1
     * @bodyParam is_active boolean Whether the group is active. Example: true
     *
     * @response 201 scenario="Category group created" {"data": {"id": 1, "key": "sdg-goals", "title": {"de": "SDG-Ziele", "en": "SDG Goals"}, "position": 1, "is_active": true, "tenant_id": 1, "created_at": "2025-01-22T10:00:00+00:00"}}
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 422 scenario="Validation error" {"message": "The key field is required.", "errors": {"key": ["The key field is required."]}}
     */
    public function store(StoreCategoryGroupRequest $request): JsonResponse
    {
        $group = CategoryGroup::create($request->validated());

        return response()->json([
            'data' => [
                'id' => $group->id,
                'key' => $group->key,
                'title' => $group->getTranslations('title'),
                'position' => $group->position,
                'is_active' => $group->is_active,
                'tenant_id' => $group->tenant_id,
                'created_at' => $group->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update a category group
     *
     * Updates an existing category group. Only groups belonging to the authenticated user's tenant can be updated.
     * The tenant_id field cannot be changed via this endpoint. Attempting to access a group from
     * another tenant returns 404 (not 403) to prevent data leakage.
     *
     * @authenticated
     *
     * @urlParam id integer required The ID of the category group. Example: 1
     *
     * @bodyParam key string Unique key identifier. Example: sdg-goals
     * @bodyParam title object Translatable title. Example: {"de": "Aktualisierter Titel"}
     * @bodyParam position integer Display position/order.
     * @bodyParam is_active boolean Whether the group is active.
     *
     * @response 200 scenario="Category group updated" {"data": {"id": 1, "key": "sdg-goals", "title": {"de": "Aktualisierter Titel", "en": "SDG Goals"}, "position": 1, "is_active": true, "tenant_id": 1, "updated_at": "2025-01-22T10:30:00+00:00"}}
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 404 scenario="Category group not found" {"message": "No query results for model [App\\Models\\CategoryGroup] 999"}
     * @response 422 scenario="Validation error" {"message": "The given data was invalid.", "errors": {"title": ["The title must be an array."]}}
     */
    public function update(UpdateCategoryGroupRequest $request, int $id): JsonResponse
    {
        $group = CategoryGroup::findOrFail($id);

        $group->update($request->validated());

        return response()->json([
            'data' => [
                'id' => $group->id,
                'key' => $group->key,
                'title' => $group->getTranslations('title'),
                'position' => $group->position,
                'is_active' => $group->is_active,
                'tenant_id' => $group->tenant_id,
                'updated_at' => $group->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete a category group
     *
     * Permanently deletes a category group. Only groups belonging to the authenticated user's tenant can be deleted.
     * Attempting to delete a group from another tenant returns 404 (not 403) to prevent data leakage.
     *
     * @authenticated
     *
     * @urlParam id integer required The ID of the category group to delete. Example: 1
     *
     * @response 204 scenario="Category group deleted"
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 404 scenario="Category group not found" {"message": "No query results for model [App\\Models\\CategoryGroup] 999"}
     */
    public function destroy(int $id): JsonResponse
    {
        $group = CategoryGroup::findOrFail($id);
        $group->delete();

        return response()->json(null, 204);
    }
}
