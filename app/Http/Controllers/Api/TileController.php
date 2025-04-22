<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TileResource;
use App\Models\Tile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TileController extends Controller
{
    /**
     * Return all tiles with their nested relations.
     */
    public function index(): ResourceCollection
    {
        $tiles = Tile::with([
            'categories',
            'backgroundPage',
            'tileYears.metrics',
        ])
            ->orderBy('position')
            ->get();

        return TileResource::collection($tiles);
    }

    /**
     * Return a single tile with its nested relations.
     */
    public function show(Tile $tile): JsonResource
    {
        $tile->load(['categories', 'backgroundPage', 'tileYears.metrics']);

        return new JsonResource($tile);
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
