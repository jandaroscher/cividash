<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SDGZielResource;
use App\Models\SDGZiel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SDGZielController extends Controller
{
    /**
     * Return a collection of SDGZielResource instances ordered by number.
     *
     * @param Request $request Request that may contain an optional `locale` query parameter for localization.
     * @return AnonymousResourceCollection A collection of SDGZielResource objects ordered by `number`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $sdgZiele = SDGZiel::orderBy('number')->get();

        return SDGZielResource::collection($sdgZiele);
    }
}
