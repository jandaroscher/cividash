<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TileResource;
use App\Models\Tile;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Schema;

/**
 * @group Public API - Tiles
 *
 * Endpoints for retrieving tile data (dashboard cards with KPIs). No authentication required.
 * Tenant context is resolved from Bearer token, request domain, or defaults to the default tenant.
 */
class TileController extends Controller
{
    /**
     * List all tiles
     *
     * Retrieves all public tiles with their categories, metric definitions, metric values, and tile years.
     * Results are ordered by position and include only tiles where `is_public = true`.
     *
     * @unauthenticated
     *
     * @queryParam locale string Locale for translated content (de or en). Example: de
     *
     * @response 200 scenario="Tiles retrieved" {"data": [{"id": 1, "slug": {"de": "energie", "en": "energy"}, "title": {"de": "Energie", "en": "Energy"}, "description": {"de": "Energieverbrauch und erneuerbare Energien", "en": "Energy consumption and renewables"}, "icon": "bolt", "position": 1, "categories": [], "metric_definitions": [], "time_periods": []}]}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Tile::query();

        if (Schema::hasColumn((new Tile)->getTable(), 'is_public')) {
            $query->where('is_public', true);
        }

        $tiles = $query->with([
            'categories' => $this->getActiveCategoriesConstraint(),
            'metricDefinitions' => function ($metricQuery) {
                $metricQuery->where('is_active', true)
                    ->with([
                        'metricValues' => function ($valueQuery) {
                            $valueQuery->where('is_active', true)
                                ->with('timePeriod');
                        },
                    ]);
            },
            'timePeriods',
        ])
            ->orderBy('position')
            ->get();

        return TileResource::collection($tiles);
    }

    /**
     * Get a single tile
     *
     * Retrieves a specific tile by its slug or ID. Includes categories, metric definitions,
     * metric values, and tile years. Slug lookup is locale-aware with fallback to other locales.
     *
     * @unauthenticated
     *
     * @urlParam slug string required The tile slug or numeric ID. Example: energie
     *
     * @queryParam locale string Locale for slug lookup and translated content (de or en). Example: de
     *
     * @response 200 scenario="Tile found" {"data": {"id": 1, "slug": {"de": "energie", "en": "energy"}, "title": {"de": "Energie", "en": "Energy"}, "description": {"de": "Energieverbrauch", "en": "Energy consumption"}, "icon": "bolt", "position": 1, "categories": [], "metric_definitions": [], "time_periods": []}}
     * @response 404 scenario="Tile not found" {"message": "No query results for model [App\\Models\\Tile]"}
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
            'categories' => $this->getActiveCategoriesConstraint(),
            'metricDefinitions' => function ($metricQuery) {
                $metricQuery->where('is_active', true)
                    ->with([
                        'metricValues' => function ($valueQuery) {
                            $valueQuery->where('is_active', true)
                                ->with('timePeriod');
                        },
                    ]);
            },
            'timePeriods',
        ]);

        return new TileResource($tile);
    }

    /**
     * Get the constraint closure for loading active categories with active groups.
     *
     * Filters categories to only include those where both the category and its
     * parent group have `is_active = true`.
     *
     * @return Closure The constraint closure for eager loading categories.
     */
    protected function getActiveCategoriesConstraint(): Closure
    {
        return function ($categoryQuery) {
            $categoryQuery
                ->where('is_active', true)
                ->whereHas('group', function ($groupQuery) {
                    $groupQuery->where('is_active', true);
                })
                ->with('group');
        };
    }

    protected function findTileBySlug(EloquentBuilder $baseQuery, string $slug, string $locale): ?Tile
    {
        $tile = (clone $baseQuery)
            ->where('slug->'.$locale, $slug)
            ->first();

        if ($tile) {
            return $tile;
        }

        // SQLite needs an explicit json_extract() comparison; the JSON arrow operator
        // alone does not reliably match there.
        if ($baseQuery->getConnection()->getDriverName() === 'sqlite') {
            $path = '$."'.$locale.'"';

            $tile = (clone $baseQuery)
                ->whereRaw('json_extract(slug, ?) = ?', [$path, $slug])
                ->first();

            if ($tile) {
                return $tile;
            }
        }

        // Driver-agnostic fallback: resolve the slug in PHP. Covers PostgreSQL,
        // MySQL/MariaDB and SQLite edge cases the SQL paths above may miss.
        return (clone $baseQuery)
            ->get()
            ->first(function (Tile $candidate) use ($slug, $locale): bool {
                $translations = $candidate->getTranslations('slug');

                return ($translations[$locale] ?? null) === $slug;
            });
    }
}
