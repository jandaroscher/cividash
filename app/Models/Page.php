<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;
use Z3d0X\FilamentFabricator\Models\Page as FabricatorPage;
use Z3d0X\FilamentFabricator\Models\Contracts\Page as PageContract;

class Page extends FabricatorPage implements PageContract
{
    use HasTranslations;
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::saved(function (self $page) {
            $page->flushContentCache();
        });
    }

    /**
     * List of translatable fields.
     */
    public array $translatable = [
        'title',
        'slug',
        'blocks',
        'meta_description',
        'meta_title',
    ];

    protected $fillable = [
        'title',
        'slug',
        'blocks',
        'meta_description',
        'meta_title',
        'meta_image',
        'layout',
        'parent_id',
        'is_public',
        'tenant_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'title' => 'array',
        'slug' => 'array',
        'blocks' => 'array',
        'meta_description' => 'array',
        'meta_title' => 'array',
        'parent_id' => 'integer',
        'is_public' => 'boolean',
    ];

    /**
     * Get the default arguments for URL generation.
     * Includes locale to differentiate URLs per language.
     */
    public function getDefaultUrlCacheArgs(): array
    {
        return [
            'locale' => app()->getLocale(),
        ];
    }

    /**
     * Get the cache key for the URL determined by this entity and the provided arguments.
     * Includes locale in the cache key.
     *
     * @param  array<string, mixed>  $args
     */
    public function getUrlCacheKey(array $args = []): string
    {
        $id = $this->id;
        $locale = $args['locale'] ?? app()->getLocale();

        return "filament-fabricator::page-url--{$id}--{$locale}";
    }

    /**
     * Get the URL determined by this entity and the provided arguments.
     * Uses translatable slug and adds /en/ prefix for EN locale.
     *
     * @param  array<string, mixed>  $args
     */
    public function getUrl(array $args = []): string
    {
        $cacheKey = $this->getUrlCacheKey($args);
        $locale = $args['locale'] ?? app()->getLocale();

        return Cache::rememberForever($cacheKey, function () use ($args, $locale) {
            /**
             * @var ?PageContract $parent
             */
            $parent = $this->parent;

            // If there's no parent page, then the "parent" URI is just the routing prefix.
            $parentUri = is_null($parent) ? (FilamentFabricator::getRoutingPrefix() ?? '/') : $parent->getUrl($args);

            // Every URI in cache has a leading slash, this ensures it's
            // present even if the prefix doesn't have it set explicitly
            $parentUri = Str::start($parentUri, '/');

            // Get translatable slug for current locale
            $slug = $this->getTranslation('slug', $locale, false);
            
            // If slug is empty for this locale, fall back to default locale
            if (empty($slug)) {
                $slug = $this->getTranslation('slug', config('app.locale', 'de'), false);
            }

            // If slug is still empty after fallback, use root slug as fallback
            // This prevents errors but should be addressed in data validation
            if (empty($slug)) {
                $slug = '/';
            }

            // This page's part of the URL (i.e. its URI) is defined as the slug.
            // Normalize slug: remove leading/trailing slashes, then add single leading slash
            $selfUri = trim($slug, '/');
            $selfUri = $selfUri === '' ? '/' : '/' . $selfUri;

            // Add /en prefix for EN locale only at root level (when parent is null)
            // This prevents duplication of /en prefix in nested pages
            // Child pages will inherit the /en prefix from their parent's URL
            if ($locale === 'en' && is_null($parent)) {
                if ($selfUri === '/') {
                    $selfUri = '/en';
                } else {
                    // Remove leading slash before adding /en prefix to avoid double slash
                    $selfUri = '/en' . $selfUri;
                }
            }

            // If the parent URI is the root, then we have nothing to glue on.
            // Therefore the page's URL is simply its URI.
            // This avoids having two consecutive slashes.
            if ($parentUri === '/') {
                return $selfUri;
            }

            // Remove any trailing slash in the parent URI since
            // every URIs we'll use has a leading slash.
            // This avoids having two consecutive slashes.
            $parentUri = rtrim($parentUri, '/');

            return "{$parentUri}{$selfUri}";
        });
    }

    /**
     * Provide argument sets used to build cache keys for all supported page URL locales.
     *
     * Each entry is an associative array with a 'locale' key; this method returns entries for 'de' and 'en'.
     *
     * @return array<string, mixed>[] Array of argument arrays for cache key generation, each containing ['locale' => string] (contains 'de' and 'en').
     */
    public function getAllUrlCacheKeysArgs(): array
    {
        return [
            ['locale' => 'de'],
            ['locale' => 'en'],
        ];
    }

    /**
     * Get the public URL for the SEO image if present.
     */
    public function getOgImageUrlAttribute(): ?string
    {
        if (empty($this->meta_image)) {
            return null;
        }

        return Storage::disk('public')->url($this->meta_image);
    }

    /**
     * Clear cached API payloads and URL caches for this page.
     */
    public function flushContentCache(): void
    {
        $tenantKey = $this->getTenantCacheKey();
        $originalTenantKey = $this->getTenantCacheKey($this->getOriginal('tenant_id'));
        $tenantKeys = array_values(array_unique([
            $tenantKey,
            $originalTenantKey,
            'public',
        ]));

        $locales = config('app.available_locales', ['de', 'en']);
        $locales = array_values(array_filter($locales));

        foreach ($tenantKeys as $key) {
            foreach ($locales as $locale) {
                Cache::forget("content_page:{$key}:{$locale}:{$this->id}");
                Cache::forget("content_pages_list:{$key}:{$locale}");
            }

            Cache::forget("content_page:{$key}:all:{$this->id}");
            Cache::forget("content_pages_list:{$key}:all");
        }

        foreach ($locales as $locale) {
            Cache::forget("filament-fabricator::page-url--{$this->id}--{$locale}");
        }

        Cache::forget("filament-fabricator::page-url--{$this->id}--all");
    }

    protected function getTenantCacheKey(?int $tenantId = null): string
    {
        $tenantId = $tenantId ?? $this->tenant_id;

        return $tenantId ? (string) $tenantId : 'public';
    }

    /**
     * Get the tenant that owns the page.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The belongs-to relationship for the Tenant model.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
