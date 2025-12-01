<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HandlungsdimensionResource;
use App\Models\Handlungsdimension;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HandlungsdimensionController extends Controller
{
    /**
     * Return a collection of HandlungsdimensionResource instances ordered by position.
     *
     * @param Request $request Request that may contain an optional `locale` query parameter for localization.
     * @return AnonymousResourceCollection A collection of HandlungsdimensionResource objects ordered by `position`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $dimensionen = Handlungsdimension::orderBy('position')->get();

        return HandlungsdimensionResource::collection($dimensionen);
    }
}
