<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ExportQueryRequest;
use App\Models\Tenant;
use App\Models\Tile;
use App\Services\Export\ExportQuery;
use App\Services\Export\ExportService;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $exportService,
    ) {}

    /**
     * Export a single tile
     *
     * Streams all metrics, values, years and category metadata for one tile
     * as JSON or CSV. The file contains a stable `schema_version` header
     * (1.0) so downstream tools can rely on the column layout.
     *
     * @group Public API - Export
     *
     * @unauthenticated
     *
     * @urlParam slug string required Tile slug (locale-aware) or numeric ID. Example: mobilitaet
     *
     * @queryParam format string Output format: `json` (default) or `csv`. Example: csv
     * @queryParam locale string Locale for translated labels (`de` or `en`). Example: de
     * @queryParam fields string[] Optional field whitelist (omit for full export). Repeat as `fields[]=tile.title&fields[]=metric.key`. No-example
     * @queryParam year_from integer Optional lower bound for value years. Example: 2015
     * @queryParam year_to integer Optional upper bound for value years. Example: 2024
     *
     * @response 200 scenario="Export streamed" "(binary file download)"
     * @response 404 scenario="Tile not found" {"message": "Tile not found"}
     * @response 422 scenario="Invalid query" {"message": "Invalid export query", "errors": {"format": ["The selected format is invalid."]}}
     */
    public function tile(ExportQueryRequest $request, string $slug): StreamedResponse
    {
        $locale = (string) $request->query('locale', 'de');
        $tile = $this->resolveTileBySlug($slug, $locale);

        if (! $tile) {
            abort(404, 'Tile not found');
        }

        $base = $request->toExportQuery();
        $query = new ExportQuery(
            format: $base->format,
            locale: $base->locale,
            fields: $base->fields,
            tileSlug: $slug,
            tileIds: [$tile->id],
            yearFrom: $base->yearFrom,
            yearTo: $base->yearTo,
        );

        $filenameBase = sprintf('tile-%s', $this->translate($tile, 'slug', $locale) ?? (string) $tile->id);

        return $this->exportService->stream(
            $query,
            $this->resolvedTenant($request),
            $filenameBase,
        );
    }

    /**
     * Export filtered tiles
     *
     * Streams multiple tiles matching the given category/tile/year filters
     * (same shape as the dashboard filter state) as JSON or CSV.
     *
     * @group Public API - Export
     *
     * @unauthenticated
     *
     * @queryParam format string `json` (default) or `csv`. Example: csv
     * @queryParam locale string `de` or `en`. Example: de
     * @queryParam fields string[] Optional field whitelist. Repeat as `fields[]=…`. No-example
     * @queryParam tiles integer[] Restrict to these tile IDs. Repeat as `tiles[]=…`. No-example
     * @queryParam categories string[] Restrict to tiles in these category keys. Repeat as `categories[]=…`. No-example
     * @queryParam year_from integer Lower year bound. Example: 2015
     * @queryParam year_to integer Upper year bound. Example: 2024
     *
     * @response 200 scenario="Export streamed" "(binary file download)"
     * @response 422 scenario="Invalid query" {"message": "Invalid export query", "errors": {"format": ["The selected format is invalid."]}}
     */
    public function tiles(ExportQueryRequest $request): StreamedResponse
    {
        $query = $request->toExportQuery();

        return $this->exportService->stream(
            $query,
            $this->resolvedTenant($request),
            'tiles',
        );
    }

    /**
     * Export the full catalog
     *
     * Streams every public tile of the resolved tenant with all metrics and
     * values. Always uses streaming; safe for large datasets.
     *
     * @group Public API - Export
     *
     * @unauthenticated
     *
     * @queryParam format string `json` (default) or `csv`. Example: csv
     * @queryParam locale string `de` or `en`. Example: de
     * @queryParam fields string[] Optional field whitelist. Repeat as `fields[]=…`. No-example
     *
     * @response 200 scenario="Catalog streamed" "(binary file download)"
     */
    public function catalog(ExportQueryRequest $request): StreamedResponse
    {
        $query = $request->toExportQuery();

        $tenant = $this->resolvedTenant($request);
        $base = $tenant ? sprintf('catalog-%s', $tenant->slug) : 'catalog';

        return $this->exportService->stream($query, $tenant, $base);
    }

    /**
     * Resolve a Tile by locale-aware slug with numeric ID fallback.
     *
     * Mirrors TileController::findTileBySlug() to handle both MySQL and
     * the SQLite-in-memory test database. Kept local to keep the shared
     * controller surface minimal.
     */
    private function resolveTileBySlug(string $slug, string $locale): ?Tile
    {
        $base = Tile::query()->where('is_public', true);

        $localesToTry = array_values(array_unique([$locale, 'de', 'en']));
        foreach ($localesToTry as $tryLocale) {
            $tile = $this->findBySlugInLocale($base, $slug, $tryLocale);
            if ($tile) {
                return $tile;
            }
        }

        if (is_numeric($slug)) {
            return (clone $base)->whereKey((int) $slug)->first();
        }

        return null;
    }

    private function findBySlugInLocale(EloquentBuilder $base, string $slug, string $locale): ?Tile
    {
        $tile = (clone $base)->where('slug->'.$locale, $slug)->first();
        if ($tile) {
            return $tile;
        }

        if ($base->getConnection()->getDriverName() === 'sqlite') {
            $path = '$."'.$locale.'"';
            $tile = (clone $base)
                ->whereRaw('json_extract(slug, ?) = ?', [$path, $slug])
                ->first();
            if ($tile) {
                return $tile;
            }

            return (clone $base)->get()->first(function (Tile $candidate) use ($slug, $locale): bool {
                $t = $candidate->getTranslations('slug');

                return ($t[$locale] ?? null) === $slug;
            });
        }

        return null;
    }

    private function translate(Tile $tile, string $attribute, string $locale): ?string
    {
        $value = $tile->getTranslation($attribute, $locale, false);
        if ($value !== null && $value !== '') {
            return (string) $value;
        }

        foreach (['de', 'en'] as $fallback) {
            if ($fallback === $locale) {
                continue;
            }
            $value = $tile->getTranslation($attribute, $fallback, false);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    private function resolvedTenant(Request $request): ?Tenant
    {
        $tenant = $request->attributes->get('resolved_tenant');

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
