<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Sharing;

use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Services\Sharing\TileShareImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TileShareImageTest extends TestCase
{
    use RefreshDatabase;

    private function tileWithValue(float $value): Tile
    {
        $tile = Tile::create([
            'title' => ['de' => 'CO2-Emissionen', 'en' => 'CO2 emissions'],
            'slug' => ['de' => 'co2', 'en' => 'co2-en'],
        ]);
        $definition = MetricDefinition::create([
            'tile_id' => $tile->id,
            'metric_key' => 'co2',
            'label' => ['de' => 'Gesamt', 'en' => 'Total'],
            'unit' => ['de' => 't', 'en' => 't'],
            'indicator_type' => 'big',
            'is_active' => true,
        ]);
        foreach (['2023' => 9999, '2024' => $value] as $key => $v) {
            $period = TimePeriod::create(['tile_id' => $tile->id, 'period_key' => $key, 'granularity' => 'year', 'label' => $key]);
            MetricValue::create(['metric_definition_id' => $definition->id, 'time_period_id' => $period->id, 'value' => $v, 'is_active' => true]);
        }

        return $tile;
    }

    private function ogImage(string $path): string
    {
        $html = $this->get($path)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="([^"]+)">#', $html);
        preg_match('#<meta property="og:image" content="([^"]+)">#', $html, $m);

        return html_entity_decode($m[1]);
    }

    public function test_tile_page_points_og_image_to_generated_preview_with_latest_value(): void
    {
        $this->tileWithValue(1234.5);

        $response = $this->get('/tiles/co2')->assertOk();

        $response->assertSee('<meta property="og:image:width" content="1200">', false);
        $response->assertSee('<meta property="og:image:height" content="630">', false);
        $response->assertSee('<meta property="og:image:alt" content="CO2-Emissionen: 1.234,5 t">', false);
        $this->assertMatchesRegularExpression('#/share/tiles/\d+\.png\?locale=de&v=[0-9a-f]{16}$#', $this->ogImage('/tiles/co2'));
    }

    public function test_english_page_uses_english_preview(): void
    {
        $this->tileWithValue(1234.5);

        $this->get('/en/tiles/co2-en')
            ->assertSee('<meta property="og:image:alt" content="CO2 emissions: 1,234.5 t">', false);
        $this->assertStringContainsString('locale=en', $this->ogImage('/en/tiles/co2-en'));
    }

    public function test_preview_url_serves_a_png_of_open_graph_size(): void
    {
        $this->tileWithValue(1234.5);
        $url = $this->ogImage('/tiles/co2');

        $response = $this->get(parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $size = getimagesizefromstring($response->getContent());
        $this->assertSame([1200, 630, 'image/png'], [$size[0], $size[1], $size['mime']]);
    }

    public function test_preview_url_changes_when_tile_data_changes(): void
    {
        $tile = $this->tileWithValue(1234.5);
        $before = $this->ogImage('/tiles/co2');

        MetricValue::where('value', 1234.5)->update(['value' => 42]);
        $afterValue = $this->ogImage('/tiles/co2');

        $tile->setTranslation('title', 'de', 'CO2 neu')->save();
        $afterTitle = $this->ogImage('/tiles/co2');

        $this->assertNotSame($before, $afterValue);
        $this->assertNotSame($afterValue, $afterTitle);
        $this->assertSame($afterTitle, $this->ogImage('/tiles/co2'));
    }

    public function test_uploaded_meta_image_wins_over_generated_preview(): void
    {
        $tile = $this->tileWithValue(1);
        $tile->update(['meta_image' => 'tiles/co2.jpg']);

        $this->assertStringEndsWith('/storage/tiles/co2.jpg', $this->ogImage('/tiles/co2'));
        $this->get('/tiles/co2')->assertDontSee('og:image:width', false);
    }

    public function test_tile_without_metrics_still_gets_a_preview(): void
    {
        $tile = Tile::create([
            'title' => ['de' => str_repeat('Sehr langer Kacheltitel ', 6)],
            'slug' => ['de' => 'text', 'en' => 'text'],
            'description' => ['de' => '<p>Beschreibung mit <strong>Markup</strong>.</p>'],
        ]);

        $this->get("/share/tiles/{$tile->id}.png")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_non_public_tile_has_no_preview(): void
    {
        $tile = $this->tileWithValue(1);
        $tile->update(['is_public' => false]);

        $this->get("/share/tiles/{$tile->id}.png")->assertNotFound();
    }

    public function test_preview_shows_the_period_the_tile_card_opens_on(): void
    {
        $tile = $this->tileWithValue(1234.5);
        $second = MetricDefinition::create([
            'tile_id' => $tile->id,
            'metric_key' => 'co2-traffic',
            'label' => ['de' => 'Verkehr'],
            'unit' => ['de' => 't'],
            'indicator_type' => 'small',
            'is_active' => true,
        ]);
        $newer = TimePeriod::create(['tile_id' => $tile->id, 'period_key' => '2025', 'granularity' => 'year', 'label' => '2025']);
        MetricValue::create(['metric_definition_id' => $second->id, 'time_period_id' => $newer->id, 'value' => 7, 'is_active' => true]);

        // The card opens on 2025, where the first metric has no value, so no stale 2024 figure.
        $this->get('/tiles/co2')->assertSee('<meta property="og:image:alt" content="CO2-Emissionen">', false);

        $first = $tile->metricDefinitions()->where('metric_key', 'co2')->first();
        MetricValue::create(['metric_definition_id' => $first->id, 'time_period_id' => $newer->id, 'value' => 99.9, 'is_active' => true]);

        $this->get('/tiles/co2')->assertSee('<meta property="og:image:alt" content="CO2-Emissionen: 99,9 t">', false);
    }

    public function test_tile_of_another_dashboard_has_no_preview(): void
    {
        $other = Tenant::factory()->create(['slug' => 'demo-city', 'domain' => 'demo-city.test']);
        $tile = Tile::create(['title' => ['de' => 'Fremd'], 'slug' => ['de' => 'fremd'], 'tenant_id' => $other->id]);

        $this->get("/share/tiles/{$tile->id}.png")->assertNotFound();
        $this->get("http://demo-city.test/share/tiles/{$tile->id}.png")->assertOk();
    }

    public function test_long_titles_and_values_stay_inside_the_image(): void
    {
        $image = app(TileShareImage::class);
        $png = $image->render([
            'layout' => 1, 'locale' => 'de', 'logo' => null, 'site_name' => 'Dashboard', 'color' => '#0d47a1',
            'title' => str_repeat('Donaudampfschifffahrt', 6).' Ende',
            'description' => '',
            'headline' => ['value' => '1.000.000.000.000.000,25', 'unit' => 't CO₂-Äquivalent', 'label' => 'Gesamt', 'period' => '2024'],
        ]);

        // The right margin (last 40px) must stay blank.
        $gd = imagecreatefromstring($png);
        for ($x = TileShareImage::WIDTH - 40; $x < TileShareImage::WIDTH; $x++) {
            for ($y = 0; $y < TileShareImage::HEIGHT; $y++) {
                $this->assertSame(0xFFFFFF, imagecolorat($gd, $x, $y) & 0xFFFFFF, "ink at {$x},{$y}");
            }
        }
    }
}
