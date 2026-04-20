<?php

namespace Tests\Unit\Services;

use App\Models\Page;
use App\Models\Tile;
use App\Services\MetaTagData;
use App\Services\MetaTagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaTagServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MetaTagService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MetaTagService::class);
    }

    // --- Route matching ---

    public function test_resolve_home_page_de(): void
    {
        Page::create([
            'title' => ['de' => 'Startseite', 'en' => 'Home'],
            'slug' => ['de' => '/', 'en' => '/'],
            'layout' => 'landingpage',
            'meta_title' => ['de' => 'Willkommen', 'en' => 'Welcome'],
            'meta_description' => ['de' => 'Das Dashboard', 'en' => 'The Dashboard'],
            'blocks' => ['de' => [], 'en' => []],
        ]);

        $meta = $this->service->resolve('/');

        $this->assertInstanceOf(MetaTagData::class, $meta);
        $this->assertSame('Willkommen', $meta->title);
        $this->assertSame('Das Dashboard', $meta->description);
        $this->assertSame('de', $meta->locale);
        $this->assertSame('website', $meta->ogType);
    }

    public function test_resolve_home_page_en(): void
    {
        Page::create([
            'title' => ['de' => 'Startseite', 'en' => 'Home'],
            'slug' => ['de' => '/', 'en' => '/'],
            'layout' => 'landingpage',
            'meta_title' => ['de' => 'Willkommen', 'en' => 'Welcome'],
            'meta_description' => ['de' => 'Das Dashboard', 'en' => 'The Dashboard'],
            'blocks' => ['de' => [], 'en' => []],
        ]);

        $meta = $this->service->resolve('/en');

        $this->assertSame('Welcome', $meta->title);
        $this->assertSame('The Dashboard', $meta->description);
        $this->assertSame('en', $meta->locale);
    }

    public function test_resolve_tiles_list_de(): void
    {
        $meta = $this->service->resolve('/tiles');

        $this->assertSame('de', $meta->locale);
        $this->assertStringContainsString('Kacheln', $meta->title);
    }

    public function test_resolve_tiles_list_en(): void
    {
        $meta = $this->service->resolve('/en/tiles');

        $this->assertSame('en', $meta->locale);
        $this->assertStringContainsString('Tiles', $meta->title);
    }

    public function test_resolve_tile_detail(): void
    {
        Tile::create([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
            'description' => ['de' => 'Energieverbrauch', 'en' => 'Energy consumption'],
            'meta_title' => ['de' => 'Energie SEO', 'en' => 'Energy SEO'],
            'meta_description' => ['de' => 'Energie Meta', 'en' => 'Energy Meta'],
        ]);

        $meta = $this->service->resolve('/tiles/energie');

        $this->assertStringContainsString('Energie SEO', $meta->title);
        $this->assertSame('Energie Meta', $meta->description);
        $this->assertSame('de', $meta->locale);
    }

    public function test_resolve_tile_detail_en(): void
    {
        Tile::create([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
            'meta_title' => ['de' => '', 'en' => ''],
            'meta_description' => ['de' => '', 'en' => ''],
        ]);

        $meta = $this->service->resolve('/en/tiles/energy');

        $this->assertStringContainsString('Energy', $meta->title);
        $this->assertSame('en', $meta->locale);
    }

    public function test_resolve_cms_page(): void
    {
        Page::create([
            'title' => ['de' => 'Impressum', 'en' => 'Imprint'],
            'slug' => ['de' => 'impressum', 'en' => 'imprint'],
            'layout' => 'default',
            'meta_title' => ['de' => 'Impressum SEO', 'en' => 'Imprint SEO'],
            'meta_description' => ['de' => 'Impressum Beschreibung', 'en' => 'Imprint Description'],
            'blocks' => ['de' => [], 'en' => []],
        ]);

        $meta = $this->service->resolve('/impressum');

        $this->assertStringContainsString('Impressum SEO', $meta->title);
        $this->assertSame('Impressum Beschreibung', $meta->description);
        $this->assertSame('de', $meta->locale);
    }

    public function test_resolve_cms_page_en(): void
    {
        Page::create([
            'title' => ['de' => 'Impressum', 'en' => 'Imprint'],
            'slug' => ['de' => 'impressum', 'en' => 'imprint'],
            'layout' => 'default',
            'meta_title' => ['de' => '', 'en' => 'Imprint SEO'],
            'meta_description' => ['de' => '', 'en' => 'Imprint Description'],
            'blocks' => ['de' => [], 'en' => []],
        ]);

        $meta = $this->service->resolve('/en/imprint');

        $this->assertStringContainsString('Imprint SEO', $meta->title);
        $this->assertSame('en', $meta->locale);
    }

    // --- Fallback behavior ---

    public function test_resolve_unknown_path_returns_defaults(): void
    {
        $meta = $this->service->resolve('/nonexistent-page');

        $this->assertInstanceOf(MetaTagData::class, $meta);
        $this->assertSame('de', $meta->locale);
        $this->assertNotEmpty($meta->title);
    }

    public function test_resolve_unknown_tile_returns_defaults(): void
    {
        $meta = $this->service->resolve('/tiles/nonexistent');

        $this->assertInstanceOf(MetaTagData::class, $meta);
        $this->assertSame('de', $meta->locale);
    }

    public function test_tile_falls_back_to_title_when_no_meta_title(): void
    {
        Tile::create([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
            'meta_title' => ['de' => '', 'en' => ''],
            'meta_description' => ['de' => '', 'en' => ''],
        ]);

        $meta = $this->service->resolve('/tiles/energie');

        $this->assertStringContainsString('Energie', $meta->title);
    }

    public function test_page_falls_back_to_title_when_no_meta_title(): void
    {
        Page::create([
            'title' => ['de' => 'Datenschutz', 'en' => 'Privacy'],
            'slug' => ['de' => 'datenschutz', 'en' => 'privacy'],
            'layout' => 'default',
            'meta_title' => ['de' => '', 'en' => ''],
            'meta_description' => ['de' => '', 'en' => ''],
            'blocks' => ['de' => [], 'en' => []],
        ]);

        $meta = $this->service->resolve('/datenschutz');

        $this->assertStringContainsString('Datenschutz', $meta->title);
    }

    // --- Locale detection ---

    public function test_locale_detection_de(): void
    {
        $meta = $this->service->resolve('/tiles');
        $this->assertSame('de', $meta->locale);
    }

    public function test_locale_detection_en_prefix(): void
    {
        $meta = $this->service->resolve('/en/tiles');
        $this->assertSame('en', $meta->locale);
    }

    public function test_locale_detection_en_root(): void
    {
        $meta = $this->service->resolve('/en');
        $this->assertSame('en', $meta->locale);
    }

    // --- Canonical URL ---

    public function test_canonical_url_has_no_query_params(): void
    {
        $meta = $this->service->resolve('/tiles?utm_source=twitter&utm_medium=social');

        $this->assertStringNotContainsString('utm_source', $meta->canonicalUrl);
        $this->assertStringNotContainsString('?', $meta->canonicalUrl);
    }

    public function test_canonical_url_is_absolute(): void
    {
        $meta = $this->service->resolve('/tiles');

        $this->assertMatchesRegularExpression('#^https?://#', $meta->canonicalUrl);
    }

    // --- Hreflang ---

    public function test_hreflang_links_contain_de_en_and_default(): void
    {
        $meta = $this->service->resolve('/tiles');

        $langs = array_column($meta->hreflangLinks, 'lang');
        $this->assertContains('de', $langs);
        $this->assertContains('en', $langs);
        $this->assertContains('x-default', $langs);
    }

    public function test_hreflang_links_are_absolute_urls(): void
    {
        $meta = $this->service->resolve('/tiles');

        foreach ($meta->hreflangLinks as $link) {
            $this->assertMatchesRegularExpression('#^https?://#', $link['url']);
        }
    }

    // --- MetaTagData DTO ---

    public function test_meta_tag_data_to_array(): void
    {
        $data = new MetaTagData(
            title: 'Test Title',
            description: 'Test Description',
            image: 'https://example.com/image.jpg',
            canonicalUrl: 'https://example.com/page',
            ogType: 'website',
            locale: 'de',
            hreflangLinks: [['lang' => 'de', 'url' => 'https://example.com/page']],
            siteName: 'Test Site',
        );

        $array = $data->toArray();

        $this->assertSame('Test Title', $array['title']);
        $this->assertSame('Test Description', $array['description']);
        $this->assertSame('https://example.com/image.jpg', $array['image']);
        $this->assertSame('https://example.com/page', $array['canonical_url']);
        $this->assertSame('website', $array['og_type']);
        $this->assertSame('de', $array['locale']);
        $this->assertSame('Test Site', $array['site_name']);
        $this->assertIsArray($array['hreflang_links']);
    }
}
