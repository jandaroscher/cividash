<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TileResource;
use App\Models\Tile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TileController extends Controller
{
    /**
     * Return a collection of TileResource instances ordered by tile position.
     *
     * The returned resources include eager-loaded `categories` and `tileYears.metrics`.
     *
     * @param Request $request Request that may contain an optional `locale` query parameter for localization.
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection A collection of TileResource objects representing tiles ordered by `position`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $tiles = Tile::with(['categories','tileYears.metrics'])
            ->orderBy('position')
            ->get();

        // Pass the locale along to the Resource via the request
        return TileResource::collection($tiles);
    }

    /**
     * Create a TileResource for the provided Tile with categories and tile years' metrics preloaded.
     *
     * @param \App\Models\Tile $tile The Tile model to wrap.
     * @return \App\Http\Resources\TileResource A resource representing the tile including its categories and tile years' metrics.
     */
    public function show(Tile $tile): TileResource
    {
        $tile->load(['categories','tileYears.metrics']);

        return new TileResource($tile);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}