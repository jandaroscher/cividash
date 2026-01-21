<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryItemResource;
use App\Models\CategoryGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SDGZielController extends Controller
{
    /**
     * List SDG category items as a collection of CategoryItemResource ordered by `position`.
     *
     * If no category group with key 'sdg' is found, an empty collection is returned.
     *
     * @return AnonymousResourceCollection A collection of CategoryItemResource instances ordered by `position`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $group = CategoryGroup::where('key', 'sdg')
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