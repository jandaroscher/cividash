<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OgMetaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_og_meta_endpoint_returns_tile_data(): void
    {
        Tile::create([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
            'meta_title' => ['de' => 'Energie SEO', 'en' => 'Energy SEO'],
            'meta_description' => ['de' => 'Energieverbrauch', 'en' => 'Energy consumption'],
        ]);

        $response = $this->getJson('/api/og-meta?path=/tiles/energie');

        $response->assertStatus(200);
        $response->assertJsonFragment(['locale' => 'de']);
        $response->assertJsonStructure([
            'title',
            'description',
            'image',
            'canonical_url',
            'og_type',
            'locale',
            'hreflang_links',
            'site_name',
        ]);
        $this->assertStringContainsString('Energie SEO', $response->json('title'));
    }

    public function test_og_meta_endpoint_returns_page_data(): void
    {
        Page::create([
            'title' => ['de' => 'Impressum', 'en' => 'Imprint'],
            'slug' => ['de' => 'impressum', 'en' => 'imprint'],
            'layout' => 'default',
            'meta_title' => ['de' => 'Impressum SEO', 'en' => ''],
            'meta_description' => ['de' => 'Rechtliche Infos', 'en' => ''],
            'blocks' => ['de' => [], 'en' => []],
        ]);

        $response = $this->getJson('/api/og-meta?path=/impressum');

        $response->assertStatus(200);
        $this->assertStringContainsString('Impressum SEO', $response->json('title'));
    }

    public function test_og_meta_endpoint_handles_en_locale(): void
    {
        Tile::create([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
            'meta_title' => ['de' => '', 'en' => 'Energy SEO'],
            'meta_description' => ['de' => '', 'en' => ''],
        ]);

        $response = $this->getJson('/api/og-meta?path=/en/tiles/energy');

        $response->assertStatus(200);
        $response->assertJsonFragment(['locale' => 'en']);
    }

    public function test_og_meta_endpoint_requires_path(): void
    {
        $response = $this->getJson('/api/og-meta');

        $response->assertStatus(422);
    }

    public function test_og_meta_endpoint_returns_defaults_for_unknown_path(): void
    {
        $response = $this->getJson('/api/og-meta?path=/nonexistent');

        $response->assertStatus(200);
        $response->assertJsonFragment(['og_type' => 'website']);
    }

    public function test_og_meta_endpoint_has_cache_headers(): void
    {
        $response = $this->getJson('/api/og-meta?path=/tiles');

        $response->assertStatus(200);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_og_meta_endpoint_strips_utm_from_canonical(): void
    {
        $response = $this->getJson('/api/og-meta?path=/tiles?utm_source=twitter');

        $response->assertStatus(200);
        $this->assertStringNotContainsString('utm_source', $response->json('canonical_url'));
    }
}
