<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ContentPagesApiLocaleTest extends TestCase
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

    public function test_german_locale_returns_german_page_data(): void
    {
        Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Impressum', 'en' => 'Imprint'],
            'slug' => ['de' => 'impressum', 'en' => 'imprint'],
            'layout' => 'default',
        ]);

        $response = $this->getJson('/api/content/pages?locale=de');

        $response->assertStatus(200);

        $pages = $response->json('data');
        $this->assertCount(1, $pages);
        $this->assertEquals('Impressum', $pages[0]['title']);
        $this->assertEquals('impressum', $pages[0]['slug']);
        $this->assertEquals('de', $response->json('meta.locale'));
    }

    public function test_english_locale_returns_english_page_data(): void
    {
        Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Impressum', 'en' => 'Imprint'],
            'slug' => ['de' => 'impressum', 'en' => 'imprint'],
            'layout' => 'default',
        ]);

        $response = $this->getJson('/api/content/pages?locale=en');

        $response->assertStatus(200);

        $pages = $response->json('data');
        $this->assertCount(1, $pages);
        $this->assertEquals('Imprint', $pages[0]['title']);
        $this->assertEquals('imprint', $pages[0]['slug']);
        $this->assertEquals('en', $response->json('meta.locale'));
    }

    public function test_no_locale_returns_both_locales(): void
    {
        Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Impressum', 'en' => 'Imprint'],
            'slug' => ['de' => 'impressum', 'en' => 'imprint'],
            'layout' => 'default',
        ]);

        $response = $this->getJson('/api/content/pages');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertArrayHasKey('de', $data);
        $this->assertArrayHasKey('en', $data);

        $this->assertEquals('Impressum', $data['de'][0]['title']);
        $this->assertEquals('impressum', $data['de'][0]['slug']);
        $this->assertEquals('Imprint', $data['en'][0]['title']);
        $this->assertEquals('imprint', $data['en'][0]['slug']);

        $meta = $response->json('meta');
        $this->assertEquals(['de', 'en'], $meta['locales']);
    }

    public function test_invalid_locale_returns_400(): void
    {
        $response = $this->getJson('/api/content/pages?locale=fr');

        $response->assertStatus(400)
            ->assertJsonPath('error', 'Invalid locale parameter. Must be "de" or "en".');
    }

    public function test_private_pages_are_excluded(): void
    {
        Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Öffentlich', 'en' => 'Public'],
            'slug' => ['de' => 'oeffentlich', 'en' => 'public'],
            'is_public' => true,
        ]);

        Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Privat', 'en' => 'Private'],
            'slug' => ['de' => 'privat', 'en' => 'private'],
            'is_public' => false,
        ]);

        $response = $this->getJson('/api/content/pages?locale=de');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Öffentlich', $response->json('data.0.title'));
    }

    public function test_pages_list_includes_meta_count(): void
    {
        Page::factory()->forTenant($this->tenant)->count(3)->create();

        $response = $this->getJson('/api/content/pages?locale=de');

        $response->assertStatus(200)
            ->assertJsonPath('meta.count', 3);
    }
}
