<?php

namespace Tests\Unit;

use App\Models\Page;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageModelCacheTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'default')->first();
    }

    public function test_flush_content_cache_clears_keys(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create();

        $tenantKey = (string) $this->tenant->id;

        // Populate cache with known keys
        Cache::put("content_page:{$tenantKey}:de:{$page->id}", 'cached-de');
        Cache::put("content_page:{$tenantKey}:en:{$page->id}", 'cached-en');
        Cache::put("content_pages_list:{$tenantKey}:de", 'list-de');
        Cache::put("content_pages_list:{$tenantKey}:en", 'list-en');
        Cache::put("filament-fabricator::page-url--{$page->id}--de", '/test');
        Cache::put("filament-fabricator::page-url--{$page->id}--en", '/en/test');

        $page->flushContentCache();

        $this->assertNull(Cache::get("content_page:{$tenantKey}:de:{$page->id}"));
        $this->assertNull(Cache::get("content_page:{$tenantKey}:en:{$page->id}"));
        $this->assertNull(Cache::get("content_pages_list:{$tenantKey}:de"));
        $this->assertNull(Cache::get("content_pages_list:{$tenantKey}:en"));
        $this->assertNull(Cache::get("filament-fabricator::page-url--{$page->id}--de"));
        $this->assertNull(Cache::get("filament-fabricator::page-url--{$page->id}--en"));
    }

    public function test_saved_event_flushes_content_cache(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Original', 'en' => 'Original'],
            'slug' => ['de' => 'original', 'en' => 'original'],
        ]);

        $tenantKey = (string) $this->tenant->id;

        // Populate cache
        Cache::put("content_page:{$tenantKey}:de:{$page->id}", 'cached-value');
        Cache::put("filament-fabricator::page-url--{$page->id}--de", '/original');

        // Save the page (triggers saved event)
        $page->title = ['de' => 'Updated', 'en' => 'Updated'];
        $page->save();

        $this->assertNull(Cache::get("content_page:{$tenantKey}:de:{$page->id}"));
        $this->assertNull(Cache::get("filament-fabricator::page-url--{$page->id}--de"));
    }

    public function test_get_url_returns_slug_for_de(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create([
            'slug' => ['de' => 'meine-seite', 'en' => 'my-page'],
        ]);

        app()->setLocale('de');

        $url = $page->getUrl(['locale' => 'de']);

        $this->assertEquals('/meine-seite', $url);
    }

    public function test_get_url_adds_en_prefix(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create([
            'slug' => ['de' => 'meine-seite', 'en' => 'my-page'],
        ]);

        app()->setLocale('en');

        $url = $page->getUrl(['locale' => 'en']);

        $this->assertEquals('/en/my-page', $url);
    }

    public function test_get_url_composes_nested_page(): void
    {
        $parent = Page::factory()->forTenant($this->tenant)->create([
            'slug' => ['de' => 'eltern', 'en' => 'parent'],
        ]);

        $child = Page::factory()->forTenant($this->tenant)->create([
            'slug' => ['de' => 'kind', 'en' => 'child'],
            'parent_id' => $parent->id,
        ]);

        app()->setLocale('de');

        $url = $child->getUrl(['locale' => 'de']);

        $this->assertEquals('/eltern/kind', $url);
    }

    public function test_get_url_composes_nested_page_en(): void
    {
        $parent = Page::factory()->forTenant($this->tenant)->create([
            'slug' => ['de' => 'eltern', 'en' => 'parent'],
        ]);

        $child = Page::factory()->forTenant($this->tenant)->create([
            'slug' => ['de' => 'kind', 'en' => 'child'],
            'parent_id' => $parent->id,
        ]);

        app()->setLocale('en');

        $url = $child->getUrl(['locale' => 'en']);

        // /en prefix is added at root level (parent), child inherits it
        $this->assertEquals('/en/parent/child', $url);
    }

    public function test_og_image_url_returns_storage_url(): void
    {
        Storage::fake('public');

        $page = Page::factory()->forTenant($this->tenant)->create([
            'meta_image' => 'og-images/test.jpg',
        ]);

        $url = $page->og_image_url;

        $this->assertNotNull($url);
        $this->assertStringContainsString('og-images/test.jpg', $url);
    }

    public function test_og_image_url_returns_null_when_empty(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create([
            'meta_image' => null,
        ]);

        $this->assertNull($page->og_image_url);
    }

    public function test_flush_also_clears_public_cache_keys(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create();

        // The public cache key should also be cleared
        Cache::put("content_page:public:de:{$page->id}", 'public-cached');
        Cache::put('content_pages_list:public:de', 'public-list');

        $page->flushContentCache();

        $this->assertNull(Cache::get("content_page:public:de:{$page->id}"));
        $this->assertNull(Cache::get('content_pages_list:public:de'));
    }
}
