<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryGroupResource;
use App\Models\CategoryGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @group Public API - Filters
 *
 * Endpoints for retrieving filter options (category groups and categories). No authentication required.
 */
class FilterController extends Controller
{
    /**
     * Get available filters
     *
     * Returns locale-specific filter labels and filterable category groups with their categories.
     * Only active and filterable groups/categories are included, ordered by position.
     *
     * @unauthenticated
     *
     * @queryParam locale string Locale for translations (de or en). Defaults to de. Example: de
     *
     * @response 200 scenario="Filters retrieved" {"data": {"labels": {"header": "Filter"}, "groups": [{"id": 1, "name": "Handlungsfeld", "slug": "handlungsfeld", "icon": "category", "categories": [{"id": 1, "name": "Energie", "slug": "energie", "color": "#ff5722"}]}]}}
     */
    public function index(Request $request): JsonResource
    {
        $locale = $request->query('locale', 'de');

        // Validate locale
        if (!in_array($locale, ['de', 'en'])) {
            $locale = 'de';
        }

        // Filter labels (only header is static)
        $labels = [
            'de' => [
                'header' => 'Filter',
            ],
            'en' => [
                'header' => 'Filter',
            ],
        ];

        $groups = CategoryGroup::query()
            ->where('is_filterable', true)
            ->where('is_active', true)
            ->with(['categories' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('position');
            }])
            ->orderBy('position')
            ->get();

        // Create a new request with locale query parameter for resources
        $resourceRequest = Request::create($request->url(), 'GET', ['locale' => $locale]);

        return new JsonResource([
            'labels' => $labels[$locale],
            'groups' => CategoryGroupResource::collection($groups)->toArray($resourceRequest),
        ]);
    }
}