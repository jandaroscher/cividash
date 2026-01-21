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
         * Return locale-specific filter labels and filterable category groups.
         *
         * Reads the `locale` query parameter ('de' or 'en') and falls back to `'de'` if absent or unsupported.
         *
         * @param \Illuminate\Http\Request $request Request that may include a `locale` query parameter ('de' or 'en').
         * @return \Illuminate\Http\Resources\Json\JsonResource An array with keys:
         *         - `labels`: array of label strings for the resolved locale,
         *         - `groups`: array of filterable category group resources (each group contains its categories ordered by position).
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