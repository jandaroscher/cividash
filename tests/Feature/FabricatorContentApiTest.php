<?php

namespace Tests\Feature;

use App\Filament\Fabricator\Layouts\LandingpageLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Page;

class FabricatorContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_api_returns_page_payload(): void
    {
        // Set locale to ensure consistent API response
        app()->setLocale('de');
        
        $page = Page::create([
            'title' => ['de' => 'API Test Page', 'en' => ''],
            'slug' => ['de' => 'api-test-page', 'en' => ''],
            'layout' => LandingpageLayout::getName(),
            'meta_title' => ['de' => 'SEO Titel', 'en' => ''],
            'meta_description' => ['de' => 'SEO Beschreibung', 'en' => ''],
            'blocks' => [
                'de' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Hero Title',
                            'subtitle' => 'Hero Subtitle',
                        ],
                    ],
                    [
                        'type' => 'tile-app',
                        'data' => [
                            'mode' => 'explore',
                            'initial_category' => 'mobility',
                        ],
                    ],
                ],
                'en' => [],
            ],
        ]);

        $response = $this->getJson("/api/content/pages/{$page->id}?locale=de");

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'id' => $page->id,
            'slug' => 'api-test-page',
            'title' => 'API Test Page',
        ]);

        $response->assertJsonPath('meta.title', 'SEO Titel');
        $response->assertJsonPath('meta.description', 'SEO Beschreibung');

        $response->assertJsonStructure([
            'id',
            'slug',
            'title',
            'layout',
            'locale',
            'tenant' => ['id', 'slug'],
            'meta' => ['description', 'image'],
            'blocks' => [
                [
                    'type',
                    'props',
                ],
            ],
            'updated_at',
        ]);

        $json = $response->json();

        $this->assertSame('hero', $json['blocks'][0]['type']);
        $this->assertSame('Hero Title', $json['blocks'][0]['props']['title'] ?? null);
        $this->assertSame('tile-app', $json['blocks'][1]['type']);
        $this->assertSame('explore', $json['blocks'][1]['props']['mode'] ?? null);
    }

    public function test_content_api_filters_inactive_blocks(): void
    {
        app()->setLocale('de');

        $page = Page::create([
            'title' => ['de' => 'API Filter Page', 'en' => ''],
            'slug' => ['de' => 'api-filter-page', 'en' => ''],
            'layout' => LandingpageLayout::getName(),
            'blocks' => [
                'de' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Visible Hero',
                            'is_active' => true,
                        ],
                    ],
                    [
                        'type' => 'text-image',
                        'data' => [
                            'text' => 'Hidden text',
                            'is_active' => false,
                        ],
                    ],
                ],
                'en' => [],
            ],
        ]);

        $response = $this->getJson("/api/content/pages/{$page->id}?locale=de");

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertCount(1, $json['blocks']);
        $this->assertSame('hero', $json['blocks'][0]['type']);
        $this->assertArrayNotHasKey('is_active', $json['blocks'][0]['props']);
    }

    public function test_content_api_returns_404_for_unknown_page(): void
    {
        $response = $this->getJson('/api/content/pages/99999');

        $response->assertStatus(404);
    }

    public function test_content_api_returns_root_page_via_root_endpoint(): void
    {
        // Create root page with slug '/'
        $rootPage = Page::create([
            'title' => ['de' => 'Root Page', 'en' => ''],
            'slug' => ['de' => '/', 'en' => ''],
            'layout' => LandingpageLayout::getName(),
            'blocks' => [
                'de' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Root Hero',
                        ],
                    ],
                ],
                'en' => [],
            ],
        ]);

        // Request /api/content/pages/root
        $response = $this->getJson('/api/content/pages/root');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'slug' => '/',
            'title' => 'Root Page',
        ]);
    }

    public function test_content_api_prioritizes_root_slug_over_home(): void
    {
        // Create both pages
        $homePage = Page::create([
            'title' => ['de' => 'Home Page', 'en' => ''],
            'slug' => ['de' => 'home', 'en' => ''],
            'layout' => LandingpageLayout::getName(),
            'blocks' => ['de' => [], 'en' => []],
        ]);

        $rootPage = Page::create([
            'title' => ['de' => 'Root Page', 'en' => ''],
            'slug' => ['de' => '/', 'en' => ''],
            'layout' => LandingpageLayout::getName(),
            'blocks' => ['de' => [], 'en' => []],
        ]);

        // Request /api/content/pages/root should return root page (prioritized)
        $response = $this->getJson('/api/content/pages/root');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'slug' => '/',
            'title' => 'Root Page',
        ]);
    }

    public function test_content_api_index_returns_all_pages(): void
    {
        // Create multiple pages
        $page1 = Page::create([
            'title' => ['de' => 'Page One', 'en' => ''],
            'slug' => ['de' => 'page-one', 'en' => ''],
            'layout' => LandingpageLayout::getName(),
            'blocks' => ['de' => [], 'en' => []],
        ]);

        $page2 = Page::create([
            'title' => ['de' => 'Page Two', 'en' => ''],
            'slug' => ['de' => 'page-two', 'en' => ''],
            'layout' => LandingpageLayout::getName(),
            'blocks' => ['de' => [], 'en' => []],
        ]);

        Page::create([
            'title' => ['de' => 'Hidden Page', 'en' => ''],
            'slug' => ['de' => 'hidden-page', 'en' => ''],
            'layout' => LandingpageLayout::getName(),
            'blocks' => ['de' => [], 'en' => []],
            'is_public' => false,
        ]);

        $rootPage = Page::create([
            'title' => ['de' => 'Root Page', 'en' => ''],
            'slug' => ['de' => '/', 'en' => ''],
            'layout' => LandingpageLayout::getName(),
            'blocks' => ['de' => [], 'en' => []],
        ]);

        // Request /api/content/pages (list endpoint) with locale parameter
        $response = $this->getJson('/api/content/pages?locale=de');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'slug', 'title', 'layout', 'parent_id', 'updated_at'],
            ],
            'meta' => ['count', 'locale', 'tenant' => ['id', 'slug']],
        ]);

        $json = $response->json();
        $this->assertCount(3, $json['data']);
        $this->assertSame(3, $json['meta']['count']);

        // Verify all pages are present
        $slugs = collect($json['data'])->pluck('slug')->toArray();
        $this->assertContains('page-one', $slugs);
        $this->assertContains('page-two', $slugs);
        $this->assertContains('/', $slugs);
        $this->assertNotContains('hidden-page', $slugs);
    }

    public function test_content_api_index_returns_empty_array_when_no_pages(): void
    {
        // Set locale for consistent test results
        app()->setLocale('en');
        
        // Request /api/content/pages (list endpoint) with no pages and locale parameter
        $response = $this->getJson('/api/content/pages?locale=en');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [],
            'meta' => [
                'count' => 0,
                'locale' => 'en',
            ],
        ]);
    }

    public function test_content_api_returns_404_for_inactive_page(): void
    {
        $page = Page::create([
            'title' => ['de' => 'Inactive Page', 'en' => ''],
            'slug' => ['de' => 'inactive-page', 'en' => ''],
            'layout' => LandingpageLayout::getName(),
            'blocks' => ['de' => [], 'en' => []],
            'is_public' => false,
        ]);

        $response = $this->getJson("/api/content/pages/{$page->id}?locale=de");

        $response->assertStatus(404);
    }
}


