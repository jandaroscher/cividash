<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryItemResource;
use App\Models\CategoryGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HandlungsdimensionController extends Controller
{
    /**
     * Retrieve CategoryItemResource objects for the "dimensions" group ordered by position.
     *
     * @param  Request  $request  Optional request (may include a `locale` query parameter).
     * @return AnonymousResourceCollection A collection of CategoryItemResource instances for the group's categories ordered by `position`; an empty collection is returned if the group or its categories are missing.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $group = CategoryGroup::where('key', 'dimensions')
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
