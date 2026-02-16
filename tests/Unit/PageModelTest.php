<?php

namespace Tests\Unit;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that Page model uses HasTranslations trait.
     */
    public function test_page_model_uses_has_translations_trait(): void
    {
        $page = new Page;

        $this->assertTrue(method_exists($page, 'getTranslation'));
        $this->assertTrue(method_exists($page, 'setTranslation'));
    }

    /**
     * Test that getTranslation() works for all translatable fields.
     */
    public function test_get_translation_works_for_all_translatable_fields(): void
    {
        $page = Page::create([
            'title' => ['de' => 'Deutscher Titel', 'en' => 'English Title'],
            'slug' => ['de' => 'deutscher-slug', 'en' => 'english-slug'],
            'blocks' => [
                'de' => [['type' => 'hero', 'data' => ['title' => 'DE Hero']]],
                'en' => [['type' => 'hero', 'data' => ['title' => 'EN Hero']]],
            ],
            'meta_description' => ['de' => 'DE Beschreibung', 'en' => 'EN Description'],
            'layout' => 'default',
        ]);

        // Test DE translations
        app()->setLocale('de');
        $this->assertEquals('Deutscher Titel', $page->getTranslation('title', 'de'));
        $this->assertEquals('deutscher-slug', $page->getTranslation('slug', 'de'));
        $this->assertIsArray($page->getTranslation('blocks', 'de'));
        $this->assertEquals('DE Beschreibung', $page->getTranslation('meta_description', 'de'));

        // Test EN translations
        app()->setLocale('en');
        $this->assertEquals('English Title', $page->getTranslation('title', 'en'));
        $this->assertEquals('english-slug', $page->getTranslation('slug', 'en'));
        $this->assertIsArray($page->getTranslation('blocks', 'en'));
        $this->assertEquals('EN Description', $page->getTranslation('meta_description', 'en'));
    }

    /**
     * Test that getUrl() includes locale in cache key and adds /en/ prefix for EN locale.
     */
    public function test_get_url_includes_locale_and_adds_en_prefix(): void
    {
        $page = Page::create([
            'title' => ['de' => 'Test Page', 'en' => 'Test Page'],
            'slug' => ['de' => 'test', 'en' => 'test'],
            'blocks' => ['de' => [], 'en' => []],
            'layout' => 'default',
        ]);

        // Test DE URL (no prefix)
        app()->setLocale('de');
        $deUrl = $page->getUrl(['locale' => 'de']);
        $this->assertStringNotContainsString('/en/', $deUrl);
        $this->assertStringContainsString('test', $deUrl);

        // Test EN URL (with /en/ prefix)
        app()->setLocale('en');
        $enUrl = $page->getUrl(['locale' => 'en']);
        $this->assertStringContainsString('/en/', $enUrl);
        $this->assertStringContainsString('test', $enUrl);
    }

    /**
     * Test that getAllUrlCacheKeysArgs() returns both locales.
     */
    public function test_get_all_url_cache_keys_args_returns_both_locales(): void
    {
        $page = new Page;

        $args = $page->getAllUrlCacheKeysArgs();

        $this->assertIsArray($args);
        $this->assertCount(2, $args);
        $this->assertContains(['locale' => 'de'], $args);
        $this->assertContains(['locale' => 'en'], $args);
    }

    public function test_saving_landingpage_forces_root_slug(): void
    {
        $page = Page::create([
            'title' => ['de' => 'Startseite', 'en' => 'Homepage'],
            'slug' => ['de' => 'homepage', 'en' => 'homepage'],
            'blocks' => ['de' => [], 'en' => []],
            'layout' => 'landingpage',
        ]);

        $this->assertEquals('/', $page->getTranslation('slug', 'de'));
        $this->assertEquals('/', $page->getTranslation('slug', 'en'));
    }

    public function test_saving_subpage_does_not_force_root_slug(): void
    {
        $page = Page::create([
            'title' => ['de' => 'Impressum', 'en' => 'Imprint'],
            'slug' => ['de' => 'impressum', 'en' => 'imprint'],
            'blocks' => ['de' => [], 'en' => []],
            'layout' => 'subpage',
        ]);

        $this->assertEquals('impressum', $page->getTranslation('slug', 'de'));
        $this->assertEquals('imprint', $page->getTranslation('slug', 'en'));
    }

    public function test_updating_page_to_landingpage_forces_root_slug(): void
    {
        $page = Page::create([
            'title' => ['de' => 'Testseite', 'en' => 'Test Page'],
            'slug' => ['de' => 'testseite', 'en' => 'test-page'],
            'blocks' => ['de' => [], 'en' => []],
            'layout' => 'default',
        ]);

        $this->assertEquals('testseite', $page->getTranslation('slug', 'de'));

        $page->layout = 'landingpage';
        $page->save();

        $this->assertEquals('/', $page->getTranslation('slug', 'de'));
        $this->assertEquals('/', $page->getTranslation('slug', 'en'));
    }
}
