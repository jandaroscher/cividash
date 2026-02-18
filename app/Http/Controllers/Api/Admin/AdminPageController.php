<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePageRequest;
use App\Http\Requests\Admin\UpdatePageRequest;
use App\Models\Page;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin API - Pages
 *
 * Endpoints for managing pages. Requires authentication with admin-api ability.
 */
class AdminPageController extends Controller
{
    /**
     * Create a new page
     *
     * Creates a new page for the authenticated user's tenant. The tenant_id is automatically
     * set from the token's tenant context and cannot be overridden via the request payload.
     *
     * @authenticated
     *
     * @bodyParam title object required Translatable title. Example: {"de": "Startseite", "en": "Home"}
     * @bodyParam slug object required Translatable slug. Example: {"de": "startseite", "en": "home"}
     * @bodyParam layout string Page layout identifier. Example: default
     * @bodyParam blocks object Translatable content blocks. Example: {"de": [{"type": "text", "data": {"content": "Hallo"}}]}
     * @bodyParam parent_id integer Parent page ID for nesting. Example: 1
     * @bodyParam is_public boolean Whether the page is publicly visible. Example: true
     * @bodyParam meta_title object Translatable SEO title. Example: {"de": "SEO Titel", "en": "SEO Title"}
     * @bodyParam meta_description object Translatable SEO description.
     * @bodyParam meta_image string URL to meta image.
     *
     * @response 201 scenario="Page created" {"data": {"id": 1, "title": {"de": "Startseite", "en": "Home"}, "slug": {"de": "startseite", "en": "home"}, "layout": "default", "blocks": {"de": [{"type": "text", "data": {"content": "Hallo"}}]}, "parent_id": null, "is_public": true, "meta_title": null, "meta_description": null, "meta_image": null, "tenant_id": 1, "created_at": "2025-01-22T10:00:00+00:00"}}
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 422 scenario="Validation error" {"message": "The title field is required.", "errors": {"title": ["The title field is required."]}}
     */
    public function store(StorePageRequest $request): JsonResponse
    {
        $page = Page::create($request->validated());

        return response()->json([
            'data' => [
                'id' => $page->id,
                'title' => $page->getTranslations('title'),
                'slug' => $page->getTranslations('slug'),
                'layout' => $page->layout,
                'blocks' => $page->getTranslations('blocks'),
                'parent_id' => $page->parent_id,
                'is_public' => $page->is_public,
                'meta_title' => $page->getTranslations('meta_title'),
                'meta_description' => $page->getTranslations('meta_description'),
                'meta_image' => $page->meta_image,
                'tenant_id' => $page->tenant_id,
                'created_at' => $page->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update a page
     *
     * Updates an existing page. Only pages belonging to the authenticated user's tenant can be updated.
     * The tenant_id field cannot be changed via this endpoint. Attempting to access a page from
     * another tenant returns 404 (not 403) to prevent data leakage.
     *
     * @authenticated
     *
     * @urlParam id integer required The ID of the page. Example: 1
     *
     * @bodyParam title object Translatable title. Example: {"de": "Aktualisierter Titel"}
     * @bodyParam slug object Translatable slug.
     * @bodyParam layout string Page layout identifier.
     * @bodyParam blocks object Translatable content blocks.
     * @bodyParam parent_id integer Parent page ID for nesting.
     * @bodyParam is_public boolean Whether the page is publicly visible.
     * @bodyParam meta_title object Translatable SEO title.
     * @bodyParam meta_description object Translatable SEO description.
     * @bodyParam meta_image string URL to meta image.
     *
     * @response 200 scenario="Page updated" {"data": {"id": 1, "title": {"de": "Aktualisierter Titel", "en": "Home"}, "slug": {"de": "startseite", "en": "home"}, "layout": "default", "blocks": {"de": [{"type": "text", "data": {"content": "Hallo"}}]}, "parent_id": null, "is_public": true, "meta_title": null, "meta_description": null, "meta_image": null, "tenant_id": 1, "updated_at": "2025-01-22T10:30:00+00:00"}}
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 404 scenario="Page not found" {"message": "No query results for model [App\\Models\\Page] 999"}
     * @response 422 scenario="Validation error" {"message": "The given data was invalid.", "errors": {"title": ["The title must be an array."]}}
     */
    public function update(UpdatePageRequest $request, int $id): JsonResponse
    {
        $page = Page::findOrFail($id);

        $page->update($request->validated());

        return response()->json([
            'data' => [
                'id' => $page->id,
                'title' => $page->getTranslations('title'),
                'slug' => $page->getTranslations('slug'),
                'layout' => $page->layout,
                'blocks' => $page->getTranslations('blocks'),
                'parent_id' => $page->parent_id,
                'is_public' => $page->is_public,
                'meta_title' => $page->getTranslations('meta_title'),
                'meta_description' => $page->getTranslations('meta_description'),
                'meta_image' => $page->meta_image,
                'tenant_id' => $page->tenant_id,
                'updated_at' => $page->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete a page
     *
     * Permanently deletes a page. Only pages belonging to the authenticated user's tenant can be deleted.
     * Attempting to delete a page from another tenant returns 404 (not 403) to prevent data leakage.
     *
     * @authenticated
     *
     * @urlParam id integer required The ID of the page to delete. Example: 1
     *
     * @response 204 scenario="Page deleted"
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 404 scenario="Page not found" {"message": "No query results for model [App\\Models\\Page] 999"}
     */
    public function destroy(int $id): JsonResponse
    {
        $page = Page::findOrFail($id);
        $page->delete();

        return response()->json(null, 204);
    }
}
