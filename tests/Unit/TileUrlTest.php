<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TileUrlTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::where('slug', 'default')->first();
    }

    public function test_get_url_returns_german_slug_url(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
        ]);

        $url = $tile->getUrl(['locale' => 'de']);

        $this->assertEquals('/tiles/energie', $url);
    }

    public function test_get_url_returns_english_slug_url_with_en_prefix(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
        ]);

        $url = $tile->getUrl(['locale' => 'en']);

        $this->assertEquals('/en/tiles/energy', $url);
    }

    public function test_get_url_auto_generates_slug_from_title(): void
    {
        // When EN slug is empty, the Tile model's booted() hook auto-generates
        // it from the English title. So getUrl('en') uses the auto-generated slug.
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Mobilität', 'en' => 'Mobility'],
            'slug' => ['de' => 'mobilitaet', 'en' => ''],
        ]);

        $url = $tile->getUrl(['locale' => 'en']);

        // 'mobility' is auto-generated from Str::slug('Mobility')
        $this->assertEquals('/en/tiles/mobility', $url);
    }

    public function test_get_url_uses_app_locale_when_no_locale_specified(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Wasser', 'en' => 'Water'],
            'slug' => ['de' => 'wasser', 'en' => 'water'],
        ]);

        // Default app locale is 'de'
        app()->setLocale('de');

        $url = $tile->getUrl();

        $this->assertEquals('/tiles/wasser', $url);
    }
}
