<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryItemResource;
use App\Models\CategoryGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Public API - Categories
 *
 * Endpoints for retrieving category data grouped by category group. No authentication required.
 * Tenant context is resolved from Bearer token, request domain, or defaults to the default tenant.
 */
class CategoryController extends Controller
{
    /**
     * List categories by group
     *
     * Retrieves all active categories for a given category group key.
     * Returns an empty collection if the group does not exist or is inactive.
     *
     * @unauthenticated
     *
     * @urlParam groupKey string required The category group key (e.g. "dimensions", "sdg_goals"). Example: dimensions
     *
     * @queryParam locale string Locale for translated content (de or en). Example: de
     *
     * @response 200 scenario="Categories retrieved" {"data": [{"id": 1, "key": "umwelt", "title": {"de": "Umwelt", "en": "Environment"}, "icon": null, "position": 1, "color": "#4CAF50", "group": {"id": 1, "key": "dimensions", "title": {"de": "Dimensionen", "en": "Dimensions"}}}]}
     * @response 200 scenario="Group not found or inactive" {"data": []}
     */
    public function showByGroup(Request $request, string $groupKey): AnonymousResourceCollection
    {
        $group = CategoryGroup::where('key', $groupKey)
            ->where('is_active', true)
            ->first();

        $items = $group
            ? $group->categories()
                ->where('is_active', true)
                ->orderBy('position')
                ->get()
            : collect();

        return CategoryItemResource::collection($items);
    }
}
