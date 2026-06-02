<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OgMetaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_home_page_contains_og_tags(): void
    {
        Page::create([
            'title' => ['de' => 'Startseite', 'en' => 'Home'],
            'slug' => ['de' => '/', 'en' => '/'],
            'layout' => 'landingpage',
            'meta_title' => ['de' => 'Willkommen', 'en' => 'Welcome'],
            'meta_description' => ['de' => 'Das Nachhaltigkeits-Dashboard', 'en' => 'The Sustainability Dashboard'],
            'blocks' => ['de' => [], 'en' => []],
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('<meta property="og:title" content="Willkommen">', false);
        $response->assertSee('<meta property="og:description" content="Das Nachhaltigkeits-Dashboard">', false);
        $response->assertSee('<meta property="og:type" content="website">', false);
        $response->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
        $response->assertSee('<link rel="canonical"', false);
    }

    public function test_en_home_page_contains_english_og_tags(): void
    {
        Page::create([
            'title' => ['de' => 'Startseite', 'en' => 'Home'],
            'slug' => ['de' => '/', 'en' => '/'],
            'layout' => 'landingpage',
            'meta_title' => ['de' => 'Willkommen', 'en' => 'Welcome'],
            'meta_description' => ['de' => 'Das Dashboard', 'en' => 'The Dashboard'],
            'blocks' => ['de' => [], 'en' => []],
        ]);

        $response = $this->get('/en');

        $response->assertStatus(200);
        $response->assertSee('<meta property="og:title" content="Welcome">', false);
        $response->assertSee('<meta property="og:description" content="The Dashboard">', false);
        $response->assertSee('og:locale" content="en_US"', false);
    }

    public function test_tile_detail_page_contains_tile_og_tags(): void
    {
        Tile::create([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
            'meta_title' => ['de' => 'Energie SEO', 'en' => 'Energy SEO'],
            'meta_description' => ['de' => 'Energieverbrauch der Stadt', 'en' => 'City energy consumption'],
        ]);

        $response = $this->get('/tiles/energie');

        $response->assertStatus(200);
        $response->assertSee('Energie SEO', false);
        $response->assertSee('Energieverbrauch der Stadt', false);
        $response->assertSee('<meta property="og:type" content="website">', false);
    }

    public function test_en_tile_detail_page_contains_english_og_tags(): void
    {
        Tile::create([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
            'meta_title' => ['de' => 'Energie SEO', 'en' => 'Energy SEO'],
            'meta_description' => ['de' => 'Energieverbrauch', 'en' => 'Energy consumption'],
        ]);

        $response = $this->get('/en/tiles/energy');

        $response->assertStatus(200);
        $response->assertSee('Energy SEO', false);
        $response->assertSee('Energy consumption', false);
    }

    public function test_cms_page_contains_page_og_tags(): void
    {
        Page::create([
            'title' => ['de' => 'Impressum', 'en' => 'Imprint'],
            'slug' => ['de' => 'impressum', 'en' => 'imprint'],
            'layout' => 'default',
            'meta_title' => ['de' => 'Impressum SEO', 'en' => 'Imprint SEO'],
            'meta_description' => ['de' => 'Rechtliche Informationen', 'en' => 'Legal information'],
            'blocks' => ['de' => [], 'en' => []],
        ]);

        $response = $this->get('/impressum');

        $response->assertStatus(200);
        $response->assertSee('Impressum SEO', false);
        $response->assertSee('Rechtliche Informationen', false);
    }

    public function test_unknown_path_returns_fallback_og_tags(): void
    {
        $response = $this->get('/nonexistent-page');

        $response->assertStatus(200);
        $response->assertSee('<meta property="og:type" content="website">', false);
        $response->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    public function test_utm_params_not_in_canonical_url(): void
    {
        Page::create([
            'title' => ['de' => 'Startseite', 'en' => 'Home'],
            'slug' => ['de' => '/', 'en' => '/'],
            'layout' => 'landingpage',
            'meta_title' => ['de' => 'Willkommen', 'en' => 'Welcome'],
            'meta_description' => ['de' => '', 'en' => ''],
            'blocks' => ['de' => [], 'en' => []],
        ]);

        $response = $this->get('/?utm_source=twitter&utm_medium=social');

        $response->assertStatus(200);
        $content = $response->getContent();
        // Canonical URL should not contain UTM params
        preg_match('/rel="canonical" href="([^"]+)"/', $content, $matches);
        $this->assertNotEmpty($matches[1] ?? '');
        $this->assertStringNotContainsString('utm_source', $matches[1]);
    }

    public function test_hreflang_links_present(): void
    {
        Page::create([
            'title' => ['de' => 'Startseite', 'en' => 'Home'],
            'slug' => ['de' => '/', 'en' => '/'],
            'layout' => 'landingpage',
            'meta_title' => ['de' => 'Willkommen', 'en' => 'Welcome'],
            'meta_description' => ['de' => '', 'en' => ''],
            'blocks' => ['de' => [], 'en' => []],
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('hreflang="de"', false);
        $response->assertSee('hreflang="en"', false);
        $response->assertSee('hreflang="x-default"', false);
    }

    public function test_og_locale_is_de_for_german_pages(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('og:locale" content="de_DE"', false);
    }
}
