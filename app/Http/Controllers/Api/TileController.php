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
    public function index(Request $request): AnonymousResourceCollection
    {
        $locale = $request->query('locale');

        $tiles = Tile::with(['categories','backgroundPage','tileYears.metrics'])
            ->orderBy('position')
            ->get();

        // Pass the locale along to the Resource via the request
        return TileResource::collection($tiles);
    }

    public function show(Request $request, Tile $tile): TileResource
    {
        $tile->load(['categories','backgroundPage','tileYears.metrics']);

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
