<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin API - Categories
 *
 * Endpoints for managing categories. Requires authentication with admin-api ability.
 */
class AdminCategoryController extends Controller
{
    /**
     * Create a new category
     *
     * Creates a new category for the authenticated user's tenant. The tenant_id is automatically
     * set from the token's tenant context and cannot be overridden via the request payload.
     *
     * @authenticated
     *
     * @bodyParam category_group_id integer required The ID of the category group this category belongs to. Example: 1
     * @bodyParam slug object required Translatable slug/label. Example: {"de": "energie", "en": "energy"}
     * @bodyParam key string Unique key identifier. Example: energy
     * @bodyParam icon string Icon identifier. Example: bolt
     * @bodyParam color string Color hex code or name. Example: #FF5733
     * @bodyParam is_active boolean Whether the category is active. Example: true
     * @bodyParam position integer Display position/order. Example: 1
     *
     * @response 201 scenario="Category created" {"data": {"id": 1, "category_group_id": 1, "slug": {"de": "energie", "en": "energy"}, "key": "energy", "icon": "bolt", "color": "#FF5733", "is_active": true, "position": 1, "tenant_id": 1, "created_at": "2025-01-22T10:00:00+00:00"}}
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 422 scenario="Validation error" {"message": "The slug field is required.", "errors": {"slug": ["The slug field is required."]}}
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return response()->json([
            'data' => [
                'id' => $category->id,
                'category_group_id' => $category->category_group_id,
                'slug' => $category->getTranslations('slug'),
                'key' => $category->key,
                'icon' => $category->icon,
                'color' => $category->color,
                'is_active' => $category->is_active,
                'position' => $category->position,
                'tenant_id' => $category->tenant_id,
                'created_at' => $category->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update a category
     *
     * Updates an existing category. Only categories belonging to the authenticated user's tenant can be updated.
     * The tenant_id field cannot be changed via this endpoint. Attempting to access a category from
     * another tenant returns 404 (not 403) to prevent data leakage.
     *
     * @authenticated
     *
     * @urlParam id integer required The ID of the category. Example: 1
     *
     * @bodyParam category_group_id integer The ID of the category group. Example: 1
     * @bodyParam slug object Translatable slug/label. Example: {"de": "aktualisiert"}
     * @bodyParam key string Unique key identifier.
     * @bodyParam icon string Icon identifier.
     * @bodyParam color string Color hex code or name.
     * @bodyParam is_active boolean Whether the category is active.
     * @bodyParam position integer Display position/order.
     *
     * @response 200 scenario="Category updated" {"data": {"id": 1, "category_group_id": 1, "slug": {"de": "aktualisiert", "en": "energy"}, "key": "energy", "icon": "bolt", "color": "#FF5733", "is_active": true, "position": 1, "tenant_id": 1, "updated_at": "2025-01-22T10:30:00+00:00"}}
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 404 scenario="Category not found" {"message": "No query results for model [App\\Models\\Category] 999"}
     * @response 422 scenario="Validation error" {"message": "The given data was invalid.", "errors": {"slug": ["The slug must be an array."]}}
     */
    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $category->update($request->validated());

        return response()->json([
            'data' => [
                'id' => $category->id,
                'category_group_id' => $category->category_group_id,
                'slug' => $category->getTranslations('slug'),
                'key' => $category->key,
                'icon' => $category->icon,
                'color' => $category->color,
                'is_active' => $category->is_active,
                'position' => $category->position,
                'tenant_id' => $category->tenant_id,
                'updated_at' => $category->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete a category
     *
     * Permanently deletes a category. Only categories belonging to the authenticated user's tenant can be deleted.
     * Attempting to delete a category from another tenant returns 404 (not 403) to prevent data leakage.
     *
     * @authenticated
     *
     * @urlParam id integer required The ID of the category to delete. Example: 1
     *
     * @response 204 scenario="Category deleted"
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 404 scenario="Category not found" {"message": "No query results for model [App\\Models\\Category] 999"}
     */
    public function destroy(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json(null, 204);
    }
}
