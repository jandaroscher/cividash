<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Tests for ETag / 304 / Last-Modified caching behavior on the content pages API.
 *
 * Note: Basic show-level ETag and 304 tests exist in ContentPagesApiEdgeCasesTest.
 * This file focuses on additional caching scenarios not covered there:
 * - Stale ETag returns fresh 200
 * - Index endpoint returns ETag header
 * - Both-locales endpoint caching
 * - Cache-Control headers
 */
class ContentPagesCachingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::where('slug', 'default')->first();
        Cache::flush();
    }

    public function test_show_returns_200_with_fresh_data_for_stale_etag(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Testseite', 'en' => 'Test page'],
            'slug' => ['de' => 'testseite', 'en' => 'test-page'],
            'layout' => 'default',
        ]);

        $response = $this->getJson("/api/content/pages/{$page->id}?locale=de");
        $response->assertOk();

        // Send a request with a stale/wrong ETag
        $response = $this->withHeader('If-None-Match', '"stale-etag-value"')
            ->getJson("/api/content/pages/{$page->id}?locale=de");

        $response->assertOk();
        $response->assertJsonPath('title', 'Testseite');
    }

    public function test_index_returns_etag_header_for_single_locale(): void
    {
        Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Seite Eins', 'en' => 'Page One'],
            'slug' => ['de' => 'seite-eins', 'en' => 'page-one'],
        ]);

        $response = $this->getJson('/api/content/pages?locale=de');

        $response->assertOk();
        $this->assertNotNull($response->headers->get('ETag'), 'Index should return an ETag header');
    }

    public function test_index_returns_etag_header_for_both_locales(): void
    {
        Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Kontakt', 'en' => 'Contact'],
            'slug' => ['de' => 'kontakt', 'en' => 'contact'],
        ]);

        $response = $this->getJson('/api/content/pages');

        $response->assertOk();
        $this->assertNotNull($response->headers->get('ETag'), 'Both-locales index should return an ETag header');
    }

    public function test_index_both_locales_returns_304_for_matching_etag(): void
    {
        Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Datenschutz', 'en' => 'Privacy'],
            'slug' => ['de' => 'datenschutz', 'en' => 'privacy'],
        ]);

        // First request to get ETag
        $response = $this->getJson('/api/content/pages');
        $response->assertOk();
        $etag = $response->headers->get('ETag');
        $this->assertNotNull($etag);

        // Second request with matching ETag
        $this->withHeader('If-None-Match', $etag)
            ->getJson('/api/content/pages')
            ->assertStatus(304);
    }

    public function test_show_sets_cache_control_public(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Cache-Test', 'en' => 'Cache Test'],
            'slug' => ['de' => 'cache-test', 'en' => 'cache-test-en'],
            'layout' => 'default',
        ]);

        $response = $this->getJson("/api/content/pages/{$page->id}?locale=de");

        $response->assertOk();
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
    }
}
