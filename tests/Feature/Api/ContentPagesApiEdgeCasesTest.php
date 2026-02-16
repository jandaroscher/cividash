<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ContentPagesApiEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::where('slug', 'default')->first();

        // Clear page cache to prevent stale results
        Cache::flush();
    }

    public function test_index_without_locale_returns_both_locales(): void
    {
        Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Impressum', 'en' => 'Imprint'],
            'slug' => ['de' => 'impressum', 'en' => 'imprint'],
            'layout' => 'default',
        ]);

        $response = $this->getJson('/api/content/pages');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('de', $data);
        $this->assertArrayHasKey('en', $data);
        $this->assertNotEmpty($data['de']);
        $this->assertNotEmpty($data['en']);

        $meta = $response->json('meta');
        $this->assertEquals(['de', 'en'], $meta['locales']);
        $this->assertArrayHasKey('de', $meta['count']);
        $this->assertArrayHasKey('en', $meta['count']);
    }

    public function test_index_with_invalid_locale_returns_400(): void
    {
        $this->getJson('/api/content/pages?locale=fr')
            ->assertStatus(400)
            ->assertJsonPath('error', 'Invalid locale parameter. Must be "de" or "en".');
    }

    public function test_show_returns_404_for_non_public_page(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->private()->create([
            'title' => ['de' => 'Geheim', 'en' => 'Secret'],
            'slug' => ['de' => 'geheim', 'en' => 'secret'],
        ]);

        $this->getJson("/api/content/pages/{$page->id}?locale=de")
            ->assertNotFound();
    }

    public function test_show_returns_etag_header(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Testseite', 'en' => 'Test page'],
            'slug' => ['de' => 'testseite', 'en' => 'test-page'],
            'layout' => 'default',
        ]);

        $response = $this->getJson("/api/content/pages/{$page->id}?locale=de");

        $response->assertOk();
        $etag = $response->headers->get('ETag');
        $this->assertNotNull($etag, 'ETag header should be present');
    }

    public function test_show_returns_304_for_matching_etag(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Testseite', 'en' => 'Test page'],
            'slug' => ['de' => 'testseite', 'en' => 'test-page'],
            'layout' => 'default',
        ]);

        // First request to get ETag
        $response = $this->getJson("/api/content/pages/{$page->id}?locale=de");
        $response->assertOk();
        $etag = $response->headers->get('ETag');

        // Second request with If-None-Match
        $this->withHeader('If-None-Match', $etag)
            ->getJson("/api/content/pages/{$page->id}?locale=de")
            ->assertStatus(304);
    }

    public function test_show_root_returns_root_page(): void
    {
        Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Startseite', 'en' => 'Home'],
            'slug' => ['de' => '/', 'en' => 'home'],
            'layout' => 'landingpage',
        ]);

        $response = $this->getJson('/api/content/pages/root?locale=de');

        $response->assertOk()
            ->assertJsonPath('slug', '/')
            ->assertJsonPath('title', 'Startseite');
    }

    public function test_show_root_falls_back_to_de_when_en_missing(): void
    {
        // Create a root page that only has a DE root slug (no EN root slug)
        // Use layout 'subpage' to avoid the model event forcing slug to '/' for all locales
        Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Startseite', 'en' => 'Home'],
            'slug' => ['de' => '/', 'en' => 'not-a-root'],
            'layout' => 'subpage',
        ]);

        // Requesting EN root should fall back to DE root page
        $response = $this->getJson('/api/content/pages/root?locale=en');

        $response->assertOk()
            ->assertJsonPath('title', 'Home');
    }

    public function test_show_root_finds_landingpage_regardless_of_initial_slug(): void
    {
        // Factory creates page with slug 'homepage', but model saving event
        // overrides it to '/' because layout is 'landingpage'
        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Startseite', 'en' => 'Homepage'],
            'slug' => ['de' => 'homepage', 'en' => 'homepage'],
            'layout' => 'landingpage',
        ]);

        // The slug should have been overridden to '/' by the model event
        $page->refresh();
        $this->assertEquals('/', $page->getTranslation('slug', 'de'));
        $this->assertEquals('/', $page->getTranslation('slug', 'en'));

        // API should find this page as root
        $response = $this->getJson('/api/content/pages/root?locale=de');

        $response->assertOk()
            ->assertJsonPath('title', 'Startseite');
    }

    public function test_show_root_returns_404_when_no_root_page(): void
    {
        // Create a page that is NOT a root page
        Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Impressum', 'en' => 'Imprint'],
            'slug' => ['de' => 'impressum', 'en' => 'imprint'],
        ]);

        $this->getJson('/api/content/pages/root?locale=de')
            ->assertNotFound();
    }

    public function test_index_etag_returns_304_on_repeat(): void
    {
        Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Testseite', 'en' => 'Test page'],
            'slug' => ['de' => 'testseite', 'en' => 'test-page'],
        ]);

        // First request to get ETag
        $response = $this->getJson('/api/content/pages?locale=de');
        $response->assertOk();
        $etag = $response->headers->get('ETag');
        $this->assertNotNull($etag);

        // Second request with If-None-Match
        $this->withHeader('If-None-Match', $etag)
            ->getJson('/api/content/pages?locale=de')
            ->assertStatus(304);
    }
}
