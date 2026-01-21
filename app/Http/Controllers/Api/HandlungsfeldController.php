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
     * Return the category items belonging to the "fields" group ordered by position.
     *
     * @param Request $request HTTP request; may include an optional `locale` query parameter for localization.
     * @return AnonymousResourceCollection Collection of CategoryItemResource instances representing the group's categories ordered by `position`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $group = CategoryGroup::where('key', 'fields')->first();
        $items = $group?->categories()->orderBy('position')->get() ?? collect();

        return CategoryItemResource::collection($items);
    }
}