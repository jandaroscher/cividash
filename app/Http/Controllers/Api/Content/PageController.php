<?php

namespace App\Http\Controllers\Api\Content;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Content\FabricatorPageTransformer;
use App\Traits\GetsTenantCacheKeySegment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class PageController extends Controller
{
    use GetsTenantCacheKeySegment;
    public function __construct(
        protected FabricatorPageTransformer $transformer
    ) {
    }

    /**
     * List all available Fabricator pages (metadata only, no blocks).
     * 
     * Query parameters:
     * - locale: Optional. Filter by locale ('de' or 'en'). If not provided, returns both locales.
     */
    public function index(Request $request): Response
    {
        $requestedLocale = $request->query('locale');
        $tenantKey = $this->getTenantCacheKeySegment();

        // Validate locale parameter if provided
        if ($requestedLocale && !in_array($requestedLocale, ['de', 'en'])) {
            return response()->json([
                'error' => 'Invalid locale parameter. Must be "de" or "en".'
            ], 400);
        }

        // If locale is specified, return single locale (backward compatible)
        if ($requestedLocale) {
            $cacheKey = "content_pages_list:{$tenantKey}:{$requestedLocale}";

            $data = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($requestedLocale) {
                $query = Page::query()
                    ->select('id', 'slug', 'title', 'layout', 'parent_id', 'updated_at');
                
                // Filter by is_public if column exists
                if (Schema::hasColumn((new Page)->getTable(), 'is_public')) {
                    $query->where('is_public', true);
                }
                
                $pages = $query->get();

                return $pages->map(function (Page $page) use ($requestedLocale) {
                    return [
                        'id' => $page->id,
                        'slug' => $page->getTranslation('slug', $requestedLocale, false),
                        'title' => $page->getTranslation('title', $requestedLocale, false),
                        'layout' => $page->layout,
                        'parent_id' => $page->parent_id,
                        'updated_at' => optional($page->updated_at)->toIso8601String(),
                    ];
                })->sortBy('title')->values()->toArray();
            });

            $response = response()->json([
                'data' => $data,
                'meta' => [
                    'count' => count($data),
                    'locale' => $requestedLocale,
                    'tenant' => $this->getTenantMeta(),
                ],
            ]);

            $etag = md5(json_encode($data));
            $response->setEtag($etag)
                ->setPublic()
                ->setMaxAge(0);

            if ($response->isNotModified($request)) {
                return $response;
            }

            return $response;
        }

        // No locale specified: return both locales
        $cacheKey = "content_pages_list:{$tenantKey}:all";

        $data = Cache::remember($cacheKey, now()->addMinutes(10), function () {
            $query = Page::query()
                ->select('id', 'slug', 'title', 'layout', 'parent_id', 'updated_at');
            
            // Filter by is_public if column exists
            if (Schema::hasColumn((new Page)->getTable(), 'is_public')) {
                $query->where('is_public', true);
            }
            
            $pages = $query->get();

            $result = ['de' => [], 'en' => []];

            foreach ($pages as $page) {
                foreach (['de', 'en'] as $locale) {
                    $result[$locale][] = [
                        'id' => $page->id,
                        'slug' => $page->getTranslation('slug', $locale, false),
                        'title' => $page->getTranslation('title', $locale, false),
                        'layout' => $page->layout,
                        'parent_id' => $page->parent_id,
                        'updated_at' => optional($page->updated_at)->toIso8601String(),
                    ];
                }
            }

            // Sort each locale array by title
            foreach (['de', 'en'] as $locale) {
                usort($result[$locale], function ($a, $b) {
                    return strcmp($a['title'] ?? '', $b['title'] ?? '');
                });
            }

            return $result;
        });

        $response = response()->json([
            'data' => $data,
            'meta' => [
                'locales' => ['de', 'en'],
                'count' => [
                    'de' => count($data['de']),
                    'en' => count($data['en']),
                ],
                'tenant' => $this->getTenantMeta(),
            ],
        ]);

        $etag = md5(json_encode($data));
        $response->setEtag($etag)
            ->setPublic()
            ->setMaxAge(0);

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    /**
     * Return the root/home Fabricator page as JSON.
     * Checks for slug '/' first, then 'home'.
     * 
     * Query parameters:
     * - locale: Optional. Filter by locale ('de' or 'en'). If not provided, returns both locales.
     */
    public function showRoot(Request $request): Response
    {
        $requestedLocale = $request->query('locale');
        $tableName = (new Page)->getTable();
        
        // Validate locale against whitelist to prevent SQL injection
        $allowedLocales = ['de', 'en'];
        
        // If locale is specified, validate and try to find root page for that locale
        if ($requestedLocale && in_array($requestedLocale, $allowedLocales)) {
            // Locale is validated, safe to use in JSON_EXTRACT
            $query = Page::query();

            if (Schema::hasColumn($tableName, 'is_public')) {
                $query->where('is_public', true);
            }

            $page = $query
                ->where(function ($q) use ($requestedLocale) {
                    $q->whereRaw("JSON_EXTRACT(slug, '$.{$requestedLocale}') = ?", ['/'])
                      ->orWhereRaw("JSON_EXTRACT(slug, '$.{$requestedLocale}') = ?", ['home']);
                })
                ->orderByRaw("CASE WHEN JSON_EXTRACT(slug, '$.{$requestedLocale}') = '/' THEN 0 ELSE 1 END")
                ->first();
            
            // If not found for requested locale, try default locale (de)
            if (!$page && $requestedLocale !== 'de') {
                $query = Page::query();

                if (Schema::hasColumn($tableName, 'is_public')) {
                    $query->where('is_public', true);
                }

                $page = $query
                    ->where(function ($q) {
                        $q->whereRaw("JSON_EXTRACT(slug, '$.de') = ?", ['/'])
                          ->orWhereRaw("JSON_EXTRACT(slug, '$.de') = ?", ['home']);
                    })
                    ->orderByRaw("CASE WHEN JSON_EXTRACT(slug, '$.de') = '/' THEN 0 ELSE 1 END")
                    ->first();
            }
        } else {
            // No locale specified or invalid: try to find any root page (prefer DE, then EN)
            // Using hardcoded locale values for safety
            $query = Page::query();

            if (Schema::hasColumn($tableName, 'is_public')) {
                $query->where('is_public', true);
            }

            $page = $query
                ->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereRaw("JSON_EXTRACT(slug, '$.de') = ?", ['/'])
                           ->orWhereRaw("JSON_EXTRACT(slug, '$.de') = ?", ['home']);
                    })
                    ->orWhere(function ($q2) {
                        $q2->whereRaw("JSON_EXTRACT(slug, '$.en') = ?", ['/'])
                           ->orWhereRaw("JSON_EXTRACT(slug, '$.en') = ?", ['home']);
                    });
                })
                ->orderByRaw("CASE WHEN JSON_EXTRACT(slug, '$.de') = '/' THEN 0 WHEN JSON_EXTRACT(slug, '$.en') = '/' THEN 1 ELSE 2 END")
                ->first();
        }

        if (! $page) {
            abort(404, 'No root or home page found');
        }

        // show() method will handle locale parameter and return appropriate response
        return $this->show($request, $page->id);
    }

    /**
     * Return a Fabricator page as JSON in a stable, tenant- and locale-aware schema.
     * Retrieves page by ID.
     * 
     * Query parameters:
     * - locale: Optional. Filter by locale ('de' or 'en'). If not provided, returns both locales.
     */
    public function show(Request $request, int $id): Response
    {
        $requestedLocale = $request->query('locale');
        $tenantKey = $this->getTenantCacheKeySegment();

        // Validate locale parameter if provided
        if ($requestedLocale && !in_array($requestedLocale, ['de', 'en'])) {
            return response()->json([
                'error' => 'Invalid locale parameter. Must be "de" or "en".'
            ], 400);
        }

        // If locale is specified, return single locale (backward compatible)
        if ($requestedLocale) {
            // Temporarily set locale for transformer
            $originalLocale = app()->getLocale();
            app()->setLocale($requestedLocale);

            $cacheKey = "content_page:{$tenantKey}:{$requestedLocale}:{$id}";

            $data = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($id) {
                $page = Page::query()
                    ->findOrFail($id);

                // Basic safeguard: if an is_public flag exists and is false, do not expose
                if (array_key_exists('is_public', $page->getAttributes()) && ! $page->is_public) {
                    abort(404);
                }

                return $this->transformer->transform($page);
            });

            // Restore original locale
            app()->setLocale($originalLocale);

            $response = response()->json($data);

            $etag = md5(json_encode($data));

            $response->setEtag($etag)
                ->setPublic()
                ->setMaxAge(0);

            if ($response->isNotModified($request)) {
                return $response;
            }

            if (! empty($data['updated_at'])) {
                $response->setLastModified(new \DateTime($data['updated_at']));
            }

            return $response;
        }

        // No locale specified: return both locales
        $cacheKey = "content_page:{$tenantKey}:all:{$id}";

        $data = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($id) {
            $page = Page::query()
                ->findOrFail($id);

            // Basic safeguard: if an is_public flag exists and is false, do not expose
            if (array_key_exists('is_public', $page->getAttributes()) && ! $page->is_public) {
                abort(404);
            }

            return $this->transformer->transformAllLocales($page);
        });

        $response = response()->json($data);

        $etag = md5(json_encode($data));

        $response->setEtag($etag)
            ->setPublic()
            ->setMaxAge(0);

        if ($response->isNotModified($request)) {
            return $response;
        }

        if (! empty($data['updated_at'])) {
            $response->setLastModified(new \DateTime($data['updated_at']));
        }

        return $response;
    }

    /**
     * Get tenant metadata safely, handling exceptions.
     *
     * @return array{id: int|string|null, slug: string|null}
     */
    protected function getTenantMeta(): array
    {
        if (function_exists('tenant')) {
            try {
                $tenant = tenant();
                if ($tenant) {
                    return [
                        'id' => $tenant->id ?? $tenant->getKey() ?? null,
                        'slug' => $tenant->slug ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                // fall through to null values
            }
        }

        return [
            'id' => null,
            'slug' => null,
        ];
    }
}


