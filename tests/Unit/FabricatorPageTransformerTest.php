<?php

namespace Tests\Unit;

use App\Models\Page;
use App\Models\Tenant;
use App\Services\Content\BlockTransformer;
use App\Services\Content\FabricatorPageTransformer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FabricatorPageTransformerTest extends TestCase
{
    use RefreshDatabase;

    private FabricatorPageTransformer $transformer;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new FabricatorPageTransformer(new BlockTransformer);
        $this->tenant = Tenant::where('slug', 'default')->first();
    }

    public function test_transform_produces_correct_single_locale_payload(): void
    {
        app()->setLocale('de');

        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Startseite', 'en' => 'Homepage'],
            'slug' => ['de' => 'startseite', 'en' => 'homepage'],
            'blocks' => [
                'de' => [['type' => 'hero', 'data' => ['heading' => 'DE Hero']]],
                'en' => [['type' => 'hero', 'data' => ['heading' => 'EN Hero']]],
            ],
            'meta_title' => ['de' => 'DE Meta Title', 'en' => 'EN Meta Title'],
            'meta_description' => ['de' => 'DE Meta Desc', 'en' => 'EN Meta Desc'],
            'layout' => 'default',
        ]);

        $result = $this->transformer->transform($page);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('slug', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('layout', $result);
        $this->assertArrayHasKey('locale', $result);
        $this->assertArrayHasKey('tenant', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('blocks', $result);
        $this->assertArrayHasKey('updated_at', $result);

        $this->assertEquals($page->id, $result['id']);
        $this->assertEquals('startseite', $result['slug']);
        $this->assertEquals('Startseite', $result['title']);
        $this->assertEquals('default', $result['layout']);
        $this->assertEquals('de', $result['locale']);
        $this->assertEquals('DE Meta Title', $result['meta']['title']);
        $this->assertEquals('DE Meta Desc', $result['meta']['description']);
    }

    public function test_transform_respects_current_locale(): void
    {
        app()->setLocale('en');

        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Startseite', 'en' => 'Homepage'],
            'slug' => ['de' => 'startseite', 'en' => 'homepage'],
            'blocks' => ['de' => [], 'en' => []],
            'layout' => 'default',
        ]);

        $result = $this->transformer->transform($page);

        $this->assertEquals('en', $result['locale']);
        $this->assertEquals('Homepage', $result['title']);
        $this->assertEquals('homepage', $result['slug']);
    }

    public function test_transform_all_locales_includes_both_languages(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Startseite', 'en' => 'Homepage'],
            'slug' => ['de' => 'startseite', 'en' => 'homepage'],
            'blocks' => [
                'de' => [['type' => 'hero', 'data' => ['heading' => 'DE Hero']]],
                'en' => [['type' => 'hero', 'data' => ['heading' => 'EN Hero']]],
            ],
            'meta_title' => ['de' => 'DE Meta', 'en' => 'EN Meta'],
            'meta_description' => ['de' => 'DE Desc', 'en' => 'EN Desc'],
            'layout' => 'default',
        ]);

        $result = $this->transformer->transformAllLocales($page);

        // Top-level keys
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('layout', $result);
        $this->assertArrayHasKey('tenant', $result);
        $this->assertArrayHasKey('updated_at', $result);
        $this->assertArrayHasKey('de', $result);
        $this->assertArrayHasKey('en', $result);

        // DE locale
        $this->assertEquals('startseite', $result['de']['slug']);
        $this->assertEquals('Startseite', $result['de']['title']);
        $this->assertEquals('DE Meta', $result['de']['meta']['title']);
        $this->assertEquals('DE Desc', $result['de']['meta']['description']);
        $this->assertCount(1, $result['de']['blocks']);

        // EN locale
        $this->assertEquals('homepage', $result['en']['slug']);
        $this->assertEquals('Homepage', $result['en']['title']);
        $this->assertEquals('EN Meta', $result['en']['meta']['title']);
        $this->assertEquals('EN Desc', $result['en']['meta']['description']);
        $this->assertCount(1, $result['en']['blocks']);
    }

    public function test_transform_handles_null_meta_fields(): void
    {
        app()->setLocale('de');

        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Test Page', 'en' => 'Test Page'],
            'slug' => ['de' => 'test', 'en' => 'test'],
            'blocks' => ['de' => [], 'en' => []],
            'layout' => 'default',
            'meta_title' => null,
            'meta_description' => null,
            'meta_image' => null,
        ]);

        $result = $this->transformer->transform($page);

        $this->assertArrayHasKey('meta', $result);
        $this->assertNull($result['meta']['image']);
    }

    public function test_transform_includes_tenant_metadata(): void
    {
        app()->setLocale('de');

        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Test', 'en' => 'Test'],
            'slug' => ['de' => 'test', 'en' => 'test'],
            'blocks' => ['de' => [], 'en' => []],
            'layout' => 'default',
        ]);

        $result = $this->transformer->transform($page);

        $this->assertArrayHasKey('tenant', $result);
        $this->assertArrayHasKey('id', $result['tenant']);
        $this->assertArrayHasKey('slug', $result['tenant']);
    }

    public function test_transform_includes_blocks_transformed_by_block_transformer(): void
    {
        app()->setLocale('de');

        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Test', 'en' => 'Test'],
            'slug' => ['de' => 'test', 'en' => 'test'],
            'blocks' => [
                'de' => [
                    ['type' => 'hero', 'data' => ['heading' => 'DE Hero']],
                    ['type' => 'text', 'data' => ['content' => 'Some text']],
                ],
                'en' => [],
            ],
            'layout' => 'default',
        ]);

        $result = $this->transformer->transform($page);

        $this->assertCount(2, $result['blocks']);
        $this->assertEquals('hero', $result['blocks'][0]['type']);
        $this->assertEquals(['heading' => 'DE Hero'], $result['blocks'][0]['props']);
        $this->assertEquals('text', $result['blocks'][1]['type']);
    }

    public function test_transform_returns_iso8601_updated_at(): void
    {
        app()->setLocale('de');

        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Test', 'en' => 'Test'],
            'slug' => ['de' => 'test', 'en' => 'test'],
            'blocks' => ['de' => [], 'en' => []],
            'layout' => 'default',
        ]);

        $result = $this->transformer->transform($page);

        $this->assertNotNull($result['updated_at']);
        // ISO 8601 format contains 'T' separator
        $this->assertStringContainsString('T', $result['updated_at']);
    }

    public function test_transform_all_locales_filters_inactive_blocks(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Test', 'en' => 'Test'],
            'slug' => ['de' => 'test', 'en' => 'test'],
            'blocks' => [
                'de' => [
                    ['type' => 'hero', 'data' => ['heading' => 'Active', 'is_active' => true]],
                    ['type' => 'text', 'data' => ['content' => 'Inactive', 'is_active' => false]],
                ],
                'en' => [
                    ['type' => 'hero', 'data' => ['heading' => 'Active EN']],
                ],
            ],
            'layout' => 'default',
        ]);

        $result = $this->transformer->transformAllLocales($page);

        $this->assertCount(1, $result['de']['blocks']);
        $this->assertEquals('hero', $result['de']['blocks'][0]['type']);
        $this->assertCount(1, $result['en']['blocks']);
    }
}
