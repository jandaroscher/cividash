<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryItemResource;
use App\Models\CategoryGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HandlungsfeldController extends Controller
{
    /**
     * Retrieve category items for the "fields" group, limited to active categories and ordered by position.
     *
     * If no active group with key "fields" exists, an empty collection is returned.
     *
     * @param Request $request HTTP request; may include an optional `locale` query parameter.
     * @return AnonymousResourceCollection Collection of CategoryItemResource instances for the group's active categories ordered by `position`, or an empty collection if the group is not found.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $group = CategoryGroup::where('key', 'fields')
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