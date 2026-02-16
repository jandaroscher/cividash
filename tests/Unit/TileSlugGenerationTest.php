<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TileSlugGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'default')->first();
    }

    public function test_saving_tile_generates_slug_from_title(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Mein Test Tile', 'en' => 'My Test Tile'],
            'slug' => ['de' => '', 'en' => ''],
        ]);

        $tile->refresh();

        $this->assertEquals('mein-test-tile', $tile->getTranslation('slug', 'de'));
        $this->assertEquals('my-test-tile', $tile->getTranslation('slug', 'en'));
    }

    public function test_existing_slugs_are_preserved(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Neuer Titel', 'en' => 'New Title'],
            'slug' => ['de' => 'existing-de-slug', 'en' => 'existing-en-slug'],
        ]);

        $tile->refresh();

        $this->assertEquals('existing-de-slug', $tile->getTranslation('slug', 'de'));
        $this->assertEquals('existing-en-slug', $tile->getTranslation('slug', 'en'));
    }

    public function test_slug_generated_for_both_locales(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Umwelt und Klima', 'en' => 'Environment and Climate'],
            'slug' => [],
        ]);

        $tile->refresh();

        $deSlugs = $tile->getTranslation('slug', 'de');
        $enSlugs = $tile->getTranslation('slug', 'en');

        $this->assertNotEmpty($deSlugs);
        $this->assertNotEmpty($enSlugs);
        $this->assertEquals(Str::slug('Umwelt und Klima'), $deSlugs);
        $this->assertEquals(Str::slug('Environment and Climate'), $enSlugs);
    }

    public function test_slug_falls_back_to_de_title_when_locale_title_missing(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Nur Deutsch'],
            'slug' => [],
        ]);

        $tile->refresh();

        // DE slug should be generated from DE title
        $this->assertEquals('nur-deutsch', $tile->getTranslation('slug', 'de'));

        // EN slug should fall back to DE title since EN title is missing
        $enSlug = $tile->getTranslation('slug', 'en');
        $this->assertEquals('nur-deutsch', $enSlug);
    }

    public function test_partial_slugs_are_filled_from_titles(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Deutsche Kachel', 'en' => 'English Tile'],
            'slug' => ['de' => 'existing-slug', 'en' => ''],
        ]);

        $tile->refresh();

        // DE slug should be preserved
        $this->assertEquals('existing-slug', $tile->getTranslation('slug', 'de'));
        // EN slug should be generated from EN title
        $this->assertEquals('english-tile', $tile->getTranslation('slug', 'en'));
    }

    public function test_slug_generation_on_update(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Original', 'en' => 'Original'],
            'slug' => ['de' => 'original', 'en' => 'original'],
        ]);

        // Clear the EN slug and save again
        $tile->slug = ['de' => 'original', 'en' => ''];
        $tile->save();
        $tile->refresh();

        // DE slug should be preserved, EN slug should be regenerated from title
        $this->assertEquals('original', $tile->getTranslation('slug', 'de'));
        $this->assertEquals('original', $tile->getTranslation('slug', 'en'));
    }
}
