<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;
use Z3d0X\FilamentFabricator\Models\Page as FabricatorPage;
use Z3d0X\FilamentFabricator\Models\Contracts\Page as PageContract;

class Page extends FabricatorPage implements PageContract
{
    use HasTranslations;

    /**
     * List of translatable fields.
     */
    public array $translatable = [
        'title',
        'slug',
        'blocks',
        'meta_description',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'title' => 'array',
        'slug' => 'array',
        'blocks' => 'array',
        'meta_description' => 'array',
        'parent_id' => 'integer',
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
     * Get all the available argument sets for the available cache keys.
     * Returns both locales (de and en) to cache URLs for all languages.
     *
     * @return array<string, mixed>[]
     */
    public function getAllUrlCacheKeysArgs(): array
    {
        return [
            ['locale' => 'de'],
            ['locale' => 'en'],
        ];
    }
}

