<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryGroupResource;
use App\Models\CategoryGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FilterController extends Controller
{
    /**
     * Provide filter labels and category group data for the requested locale.
     *
     * The response contains a 'labels' key with locale-specific label strings and a
     * 'groups' key with an array representation of filterable category groups
     * (each group includes its categories ordered by position). Locale is read
     * from the `locale` query parameter and defaults to 'de'; unsupported locales
     * fall back to 'de'.
     *
     * @param \Illuminate\Http\Request $request Request that may include a `locale` query parameter ('de' or 'en').
     * @return \Illuminate\Http\Resources\Json\JsonResource A resource with keys:
     *         - `labels`: array of locale-specific labels,
     *         - `groups`: array of category group resources prepared for the resolved locale.
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
            ->with(['categories' => function ($query) {
                $query->orderBy('position');
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
