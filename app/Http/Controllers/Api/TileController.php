<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TileResource;
use App\Models\Tile;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Schema;

class TileController extends Controller
{
    /**
         * Retrieve tiles ordered by their position with required relations loaded for TileResource.
         *
         * Loads categories.group, active metricDefinitions with their active metricValues and associated tileYear, and tileYears.
         * If the tiles table has an `is_public` column, only tiles with `is_public = true` are included.
         * The request is forwarded to the resource so a `locale` query parameter can be used by the TileResource.
         *
         * @param Request $request Optional request that may contain a `locale` query parameter used by the resource.
         * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection A collection of TileResource objects ordered by `position`.
         */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Tile::query();

        if (Schema::hasColumn((new Tile)->getTable(), 'is_public')) {
            $query->where('is_public', true);
        }

        $tiles = $query->with([
            'categories.group',
            'metricDefinitions' => function ($metricQuery) {
                $metricQuery->where('is_active', true)
                    ->with([
                        'metricValues' => function ($valueQuery) {
                            $valueQuery->where('is_active', true)
                                ->with('tileYear');
                        },
                    ]);
            }, // New structure (filtered)
            'tileYears', // For years array
        ])
            ->orderBy('position')
            ->get();

        // Pass the locale along to the Resource via the request
        return TileResource::collection($tiles);
    }

    /**
     * Retrieve a tile by slug (locale-aware) and return it as a TileResource.
     *
     * Attempts to resolve the tile using the optional `locale` query parameter (allowed: "de", "en")
     * with fallbacks. If not found by slug and the slug is numeric, the method will try to load by primary key.
     * If no tile is found, a 404 response is triggered. The returned resource includes preloaded relations:
     * categories.group, active metricDefinitions with their active metricValues and tileYear, and tileYears.
     *
     * @param \Illuminate\Http\Request $request HTTP request (may include `locale` query parameter).
     * @param string $slug The tile slug (or numeric id as fallback) to look up.
     * @return \App\Http\Resources\TileResource The resolved tile wrapped as a TileResource with related data loaded.
     */
    public function show(Request $request, string $slug): TileResource
    {
        $locale = $request->query('locale');
        $allowedLocales = ['de', 'en'];
        $tableName = (new Tile)->getTable();

        $baseQuery = Tile::query();

        if (Schema::hasColumn($tableName, 'is_public')) {
            $baseQuery->where('is_public', true);
        }

        $tile = null;

        if ($locale && in_array($locale, $allowedLocales, true)) {
            $localesToTry = array_values(array_unique(array_filter([
                $locale,
                'de',
                'en',
            ])));

            foreach ($localesToTry as $lookupLocale) {
                $tile = $this->findTileBySlug($baseQuery, $slug, $lookupLocale);

                if ($tile) {
                    break;
                }
            }
        } else {
            $tile = $this->findTileBySlug($baseQuery, $slug, 'de')
                ?? $this->findTileBySlug($baseQuery, $slug, 'en');
        }

        if (! $tile && is_numeric($slug)) {
            $tile = (clone $baseQuery)->whereKey((int) $slug)->first();
        }

        if (! $tile) {
            abort(404);
        }

        $tile->load([
            'categories.group',
            'metricDefinitions' => function ($metricQuery) {
                $metricQuery->where('is_active', true)
                    ->with([
                        'metricValues' => function ($valueQuery) {
                            $valueQuery->where('is_active', true)
                                ->with('tileYear');
                        },
                    ]);
            }, // New structure (filtered)
            'tileYears', // For years array
        ]);

        return new TileResource($tile);
    }

    protected function findTileBySlug(EloquentBuilder $baseQuery, string $slug, string $locale): ?Tile
    {
        $tile = (clone $baseQuery)
            ->where('slug->' . $locale, $slug)
            ->first();

        if ($tile || $baseQuery->getConnection()->getDriverName() !== 'sqlite') {
            return $tile;
        }

        $path = '$."' . $locale . '"';

        $tile = (clone $baseQuery)
            ->whereRaw('json_extract(slug, ?) = ?', [$path, $slug])
            ->first();

        if ($tile) {
            return $tile;
        }

        return (clone $baseQuery)
            ->get()
            ->first(function (Tile $candidate) use ($slug, $locale): bool {
                $translations = $candidate->getTranslations('slug');
                return ($translations[$locale] ?? null) === $slug;
            });
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