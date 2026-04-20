<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Tile;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Storage;

class MetaTagService
{
    public function __construct(
        protected GeneralSettings $generalSettings,
    ) {}

    /**
     * Resolve OG meta data for a given URL path.
     */
    public function resolve(string $path, ?string $host = null): MetaTagData
    {
        // Strip query string if accidentally included
        $path = strtok($path, '?') ?: '/';

        // Normalize path: ensure leading slash, strip trailing slash
        $path = '/'.ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        // Detect locale from path
        $locale = $this->detectLocale($path);

        // Strip locale prefix to get clean path
        $cleanPath = $this->stripLocalePrefix($path);

        // Route matching (same priority as Vue Router)
        $meta = $this->matchRoute($cleanPath, $locale, $host);

        return $meta;
    }

    protected function detectLocale(string $path): string
    {
        if (preg_match('#^/en(/|$)#', $path)) {
            return 'en';
        }

        return 'de';
    }

    protected function stripLocalePrefix(string $path): string
    {
        if (preg_match('#^/en(/.*|$)#', $path, $matches)) {
            $stripped = $matches[1] ?? '/';

            return $stripped === '' ? '/' : $stripped;
        }

        return $path;
    }

    protected function matchRoute(string $cleanPath, string $locale, ?string $host): MetaTagData
    {
        // Home page
        if ($cleanPath === '/') {
            return $this->resolveForHome($locale, $host);
        }

        // Tiles list
        if ($cleanPath === '/tiles') {
            return $this->resolveForTilesList($locale, $host);
        }

        // Tile detail: /tiles/{slug}
        if (preg_match('#^/tiles/([^/]+)$#', $cleanPath, $matches)) {
            return $this->resolveForTile($matches[1], $locale, $host);
        }

        // CMS page: /{slug+}
        $slugPath = ltrim($cleanPath, '/');
        if ($slugPath !== '') {
            return $this->resolveForPage($slugPath, $locale, $host);
        }

        return $this->getDefaults($locale, $host);
    }

    public function resolveForHome(string $locale, ?string $host = null): MetaTagData
    {
        // Find the landing page (layout = 'landingpage')
        $page = Page::where('layout', 'landingpage')
            ->where('is_public', true)
            ->first();

        if ($page) {
            return $this->buildFromPage($page, $locale, '/', $host);
        }

        return $this->getDefaults($locale, $host);
    }

    public function resolveForTilesList(string $locale, ?string $host = null): MetaTagData
    {
        $siteName = $this->getSiteName();
        $title = $locale === 'en'
            ? "Tiles - {$siteName}"
            : "Kacheln - {$siteName}";

        $path = $locale === 'en' ? '/en/tiles' : '/tiles';

        return new MetaTagData(
            title: $title,
            description: '',
            image: null,
            canonicalUrl: $this->buildCanonicalUrl($path, $host),
            ogType: 'website',
            locale: $locale,
            hreflangLinks: $this->buildSimpleHreflangLinks('/tiles', '/en/tiles', $host),
            siteName: $siteName,
        );
    }

    public function resolveForTile(string $slug, string $locale, ?string $host = null): MetaTagData
    {
        $tile = Tile::where("slug->{$locale}", $slug)
            ->where('is_public', true)
            ->first();

        // Fallback: try finding by any locale slug
        if (! $tile) {
            $tile = Tile::where('is_public', true)
                ->where(fn ($q) => $q->where('slug->de', $slug)->orWhere('slug->en', $slug))
                ->first();
        }

        if (! $tile) {
            return $this->getDefaults($locale, $host);
        }

        return $this->buildFromTile($tile, $locale, $host);
    }

    public function resolveForPage(string $slugPath, string $locale, ?string $host = null): MetaTagData
    {
        $page = $this->findPageBySlugPath($slugPath, $locale);

        if (! $page) {
            return $this->getDefaults($locale, $host);
        }

        if (! $page) {
            return $this->getDefaults($locale, $host);
        }

        // Use the page's resolved URL for canonical (not the raw request slug)
        $path = $page->getUrl(['locale' => $locale]);

        return $this->buildFromPage($page, $locale, $path, $host);
    }

    protected function buildFromTile(Tile $tile, string $locale, ?string $host): MetaTagData
    {
        $siteName = $this->getSiteName();

        $metaTitle = $tile->getTranslation('meta_title', $locale, false);
        $fallbackTitle = $tile->getTranslation('title', $locale, false)
            ?: $tile->getTranslation('title', 'de', false);
        $title = $metaTitle ?: $fallbackTitle;
        if ($siteName && $title) {
            $title = "{$title} - {$siteName}";
        }

        $description = $tile->getTranslation('meta_description', $locale, false)
            ?: $tile->getTranslation('description', $locale, false)
            ?: '';

        $image = $tile->meta_image
            ? Storage::disk('public')->url($tile->meta_image)
            : null;

        $path = $tile->getUrl(['locale' => $locale]);
        $canonicalUrl = $this->buildCanonicalUrl($path, $host);

        // Build hreflang links using the tile's own URL methods
        $dePath = $tile->getUrl(['locale' => 'de']);
        $enPath = $tile->getUrl(['locale' => 'en']);

        return new MetaTagData(
            title: $title ?: $siteName,
            description: $description,
            image: $image,
            canonicalUrl: $canonicalUrl,
            ogType: 'website',
            locale: $locale,
            hreflangLinks: $this->buildSimpleHreflangLinks($dePath, $enPath, $host),
            siteName: $siteName,
        );
    }

    protected function buildFromPage(Page $page, string $locale, string $path, ?string $host): MetaTagData
    {
        $siteName = $this->getSiteName();

        $metaTitle = $page->getTranslation('meta_title', $locale, false);
        $fallbackTitle = $page->getTranslation('title', $locale, false)
            ?: $page->getTranslation('title', 'de', false);
        $title = $metaTitle ?: $fallbackTitle;

        // Don't append site name for the home page (landingpage layout)
        if ($page->layout !== 'landingpage' && $siteName && $title) {
            $title = "{$title} - {$siteName}";
        }

        $description = $page->getTranslation('meta_description', $locale, false) ?: '';
        $image = $page->og_image_url;

        $canonicalUrl = $this->buildCanonicalUrl($path, $host);

        // Build hreflang using the page's own URL methods
        $dePath = $page->getUrl(['locale' => 'de']);
        $enPath = $page->getUrl(['locale' => 'en']);

        return new MetaTagData(
            title: $title ?: $siteName,
            description: $description,
            image: $image,
            canonicalUrl: $canonicalUrl,
            ogType: 'website',
            locale: $locale,
            hreflangLinks: $this->buildSimpleHreflangLinks($dePath, $enPath, $host),
            siteName: $siteName,
        );
    }

    /**
     * Find a page by slug path, supporting both flat and nested (hierarchical) slugs.
     * For nested paths like "legal/datenschutz", tries the full path first, then
     * falls back to the last segment and verifies the resolved URL matches.
     */
    protected function findPageBySlugPath(string $slugPath, string $locale): ?Page
    {
        $locales = [$locale, $locale === 'en' ? 'de' : 'en'];

        foreach ($locales as $loc) {
            // Try exact slug match (works for flat, single-segment pages)
            $page = Page::where("slug->{$loc}", $slugPath)
                ->where('is_public', true)
                ->first();

            if ($page) {
                return $page;
            }
        }

        // For nested paths: try matching the last segment, then verify the full URL
        if (str_contains($slugPath, '/')) {
            $lastSegment = basename($slugPath);
            $expectedPath = $locale === 'en' ? "/en/{$slugPath}" : "/{$slugPath}";

            foreach ($locales as $loc) {
                $candidates = Page::where("slug->{$loc}", $lastSegment)
                    ->where('is_public', true)
                    ->get();

                foreach ($candidates as $candidate) {
                    $resolvedUrl = $candidate->getUrl(['locale' => $locale]);
                    if (rtrim($resolvedUrl, '/') === rtrim($expectedPath, '/')) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    public function getDefaults(string $locale = 'de', ?string $host = null): MetaTagData
    {
        $siteName = $this->getSiteName();
        $path = $locale === 'en' ? '/en' : '/';

        return new MetaTagData(
            title: $siteName,
            description: '',
            image: null,
            canonicalUrl: $this->buildCanonicalUrl($path, $host),
            ogType: 'website',
            locale: $locale,
            hreflangLinks: $this->buildSimpleHreflangLinks('/', '/en', $host),
            siteName: $siteName,
        );
    }

    public function buildCanonicalUrl(string $path, ?string $host = null): string
    {
        $base = $this->getBaseUrl($host);

        // Ensure path starts with /
        $path = '/'.ltrim($path, '/');

        // Remove trailing slash except for root
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return rtrim($base, '/').$path;
    }

    /**
     * @return array<int, array{lang: string, url: string}>
     */
    protected function buildSimpleHreflangLinks(string $dePath, string $enPath, ?string $host): array
    {
        $base = $this->getBaseUrl($host);
        $base = rtrim($base, '/');

        return [
            ['lang' => 'de', 'url' => $base.$dePath],
            ['lang' => 'en', 'url' => $base.$enPath],
            ['lang' => 'x-default', 'url' => $base.$dePath],
        ];
    }

    protected function getBaseUrl(?string $host): string
    {
        // Try tenant domain first
        $tenant = $this->resolveCurrentTenant();

        if ($tenant && $tenant->domain) {
            $scheme = request()->getScheme();

            return "{$scheme}://{$tenant->domain}";
        }

        // If host is provided, use it
        if ($host) {
            $scheme = request()->getScheme();

            return "{$scheme}://{$host}";
        }

        return rtrim(config('app.url', 'http://localhost'), '/');
    }

    protected function getSiteName(): string
    {
        try {
            $name = $this->generalSettings->site_name;

            return ! empty($name) ? $name : config('app.name', 'Dashboard');
        } catch (\Throwable) {
            return config('app.name', 'Dashboard');
        }
    }

    protected function resolveCurrentTenant(): ?object
    {
        $request = rescue(fn () => app('request'), null, false);

        return $request?->attributes?->get('resolved_tenant');
    }
}
