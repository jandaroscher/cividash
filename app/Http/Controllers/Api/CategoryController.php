<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryItemResource;
use App\Models\CategoryGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
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
