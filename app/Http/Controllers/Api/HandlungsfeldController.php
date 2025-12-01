<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HandlungsfeldResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HandlungsfeldController extends Controller
{
    /**
     * Return a collection of HandlungsfeldResource instances ordered by position.
     *
     * @param Request $request Request that may contain an optional `locale` query parameter for localization.
     * @return AnonymousResourceCollection A collection of HandlungsfeldResource objects ordered by `position`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $handlungsfelder = Category::orderBy('position')->get();

        return HandlungsfeldResource::collection($handlungsfelder);
    }
}
