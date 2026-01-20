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
     * Return a collection of TileResource instances ordered by tile position.
     *
     * The returned resources include eager-loaded `categories` and `tileYears.metrics`.
     *
     * @param Request $request Request that may contain an optional `locale` query parameter for localization.
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection A collection of TileResource objects representing tiles ordered by `position`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Tile::query();

        if (Schema::hasColumn((new Tile)->getTable(), 'is_public')) {
            $query->where('is_public', true);
        }

        $tiles = $query->with([
            'categories',
            'handlungsdimension',
            'sdgZiele',
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
     * Create a TileResource for the provided Tile with categories and tile years' metrics preloaded.
     *
     * @param \App\Models\Tile $tile The Tile model to wrap.
     * @return \App\Http\Resources\TileResource A resource representing the tile including its categories and tile years' metrics.
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
            'categories',
            'handlungsdimension',
            'sdgZiele',
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