<?php

namespace App\Observers;

use App\Models\Page;
use Illuminate\Support\Facades\Cache;

class PageObserver
{
    /**
     * Handle the Page "created" event.
     */
    public function created(Page $page): void
    {
        $this->invalidateAllCaches($page);
    }

    /**
     * Handle the Page "updated" event.
     */
    public function updated(Page $page): void
    {
        $this->invalidateAllCaches($page);

        // Check if slug or parent_id changed, which affects child page URLs
        $slugChanged = $page->wasChanged('slug');
        $parentIdChanged = $page->wasChanged('parent_id');

        if ($slugChanged || $parentIdChanged) {
            // Invalidate all child pages recursively since their URLs depend on parent URL
            $this->invalidateChildPagesUrlCache($page);
        }
    }

    /**
     * Handle the Page "deleted" event.
     */
    public function deleted(Page $page): void
    {
        $this->invalidateAllCaches($page);
    }

    /**
     * Invalidate all cache keys for a page (URL cache and API cache).
     * This includes cache keys for all locales (de and en).
     */
    protected function invalidateAllCaches(Page $page): void
    {
        $this->invalidateUrlCache($page);
        $this->invalidateApiCache($page);
    }

    /**
     * Invalidate all URL cache keys for a page.
     * This includes cache keys for all locales (de and en).
     */
    protected function invalidateUrlCache(Page $page): void
    {
        $locales = config('app.available_locales', ['de', 'en']);
        $locales = array_values(array_filter($locales));

        foreach ($locales as $locale) {
            $cacheKey = $page->getUrlCacheKey(['locale' => $locale]);
            Cache::forget($cacheKey);
        }
    }

    /**
     * Recursively invalidate URL cache for all child pages.
     * This is necessary because child page URLs are computed using parent->getUrl(),
     * so when a parent's slug or parent_id changes, all descendant URLs become stale.
     *
     * @param  Page  $page  The parent page whose children should be invalidated
     */
    protected function invalidateChildPagesUrlCache(Page $page): void
    {
        // Load direct children
        $children = $page->children()->get();

        foreach ($children as $child) {
            // Invalidate this child's URL cache
            $this->invalidateUrlCache($child);

            // Recursively invalidate this child's children
            $this->invalidateChildPagesUrlCache($child);
        }
    }

    /**
     * Invalidate all API cache keys for a page.
     * This includes cache keys for individual pages and page lists for all locales.
     */
    protected function invalidateApiCache(Page $page): void
    {
        $page->flushContentCache();
    }
}
