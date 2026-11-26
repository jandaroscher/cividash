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
use App\Settings\BrandingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

        $this->get('/tiles/co2')->assertSee('<meta property="og:image:alt" content="CO2-Emissionen: 99,9 t; 7 t">', false);
    }

    public function test_preview_lists_further_metrics_of_the_same_period(): void
    {
        $tile = $this->tileWithValue(1234.5);
        $period = TimePeriod::where('period_key', '2024')->first();
        foreach (['Verkehr' => 400, 'Industrie' => null, 'Haushalte' => 300.25, 'Gewerbe' => 200, 'Sonstige' => 100] as $label => $value) {
            $definition = MetricDefinition::create([
                'tile_id' => $tile->id, 'metric_key' => $label, 'label' => ['de' => $label], 'unit' => ['de' => 't'],
                'indicator_type' => 'small', 'is_active' => true,
            ]);
            MetricValue::create(['metric_definition_id' => $definition->id, 'time_period_id' => $period->id, 'value' => $value, 'is_active' => true]);
        }

        // Metrics without a value are skipped, and at most four values are shown.
        $this->get('/tiles/co2')->assertSee('<meta property="og:image:alt" content="CO2-Emissionen: 1.234,5 t; 400 t; 300,25 t; 200 t">', false);
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
            'layout' => 1, 'locale' => 'de', 'logo' => null, 'site_name' => 'Dashboard', 'colors' => $image->colors([]),
            'title' => str_repeat('Donaudampfschifffahrt', 6).' Ende',
            'description' => '',
            'headline' => ['value' => '1.000.000.000.000.000,25', 'unit' => 't CO₂-Äquivalent', 'label' => 'Gesamt', 'period' => '2024'],
            'more' => array_fill(0, 3, ['value' => '999.999.999.999,99', 'unit' => 'kWh pro Einwohner', 'label' => str_repeat('Lange Bezeichnung ', 4)]),
        ]);

        // Text keeps its 64px side padding: right of the head text only the surface shows,
        // right of the panel text only the panel.
        $gd = imagecreatefromstring($png);
        for ($x = TileShareImage::WIDTH - 94; $x < TileShareImage::WIDTH; $x++) {
            for ($y = 20; $y < TileShareImage::HEIGHT; $y++) {
                // The further metrics are too wide for their columns, so the panel keeps its small height.
                $inPanel = $x >= 30 && $x < TileShareImage::WIDTH - 30 && $y >= 360 && $y < 600;
                if (! $inPanel && $y < 360 && $x < TileShareImage::WIDTH - 64) {
                    continue;
                }
                $expected = $inPanel ? 0x0D47A1 : 0xFFFFFF;
                $this->assertSame($expected, imagecolorat($gd, $x, $y) & 0xFFFFFF, "ink at {$x},{$y}");
            }
        }
    }

    public function test_a_long_unit_is_cut_and_the_value_stays_whole(): void
    {
        $image = app(TileShareImage::class);
        $render = fn (string $unit) => imagecreatefromstring($image->render([
            'layout' => 1, 'locale' => 'de', 'logo' => null, 'site_name' => 'Dashboard', 'colors' => $image->colors([]),
            'title' => 'Titel', 'description' => '', 'more' => [],
            'headline' => ['value' => '1.298.450.000.000.000,25', 'unit' => $unit, 'label' => 'Gesamt', 'period' => '2024'],
        ]));
        $short = $render('kWh pro Einwohnerin und Einwohner');
        $long = $render('kWh pro Einwohnerin und Einwohner im Jahresmittel der Region');

        // Both units are too long for the panel; how much of them is cut must not change the number.
        for ($x = 0; $x < 820; $x++) {
            for ($y = 20; $y < TileShareImage::HEIGHT; $y++) {
                $this->assertSame(imagecolorat($short, $x, $y), imagecolorat($long, $x, $y), "value differs at {$x},{$y}");
            }
        }
    }

    public function test_cut_text_keeps_accented_letters_whole(): void
    {
        $image = app(TileShareImage::class);
        $width = fn (string $text) => (new \ReflectionMethod($image, 'width'))->invoke($image, $text, 28, false);
        $fitWords = fn (string $text, int $max) => (new \ReflectionMethod($image, 'fitWords'))->invoke($image, $text, 28, $max, false);

        $this->assertSame('Hafen MACEIÓ…', $fitWords('Hafen MACEIÓ Ankunft und Abfahrt', $width('Hafen MACEIÓ…') + 2));
        $this->assertSame('Label…', $fitWords('Label · 2024', $width('Label · 20')));
    }

    public function test_further_metrics_are_left_out_when_one_is_too_wide(): void
    {
        $image = app(TileShareImage::class);
        $content = [
            'layout' => 1, 'locale' => 'de', 'logo' => null, 'site_name' => 'Dashboard', 'colors' => $image->colors([]),
            'title' => 'Titel', 'description' => '',
            'headline' => ['value' => '12', 'unit' => 't', 'label' => 'Gesamt', 'period' => '2024'],
            'more' => [['value' => '1', 'unit' => 't', 'label' => 'Kurz']],
        ];
        $panelTop = function (string $png) {
            $gd = imagecreatefromstring($png);

            return collect(range(20, TileShareImage::HEIGHT - 1))->first(fn ($y) => (imagecolorat($gd, 600, $y) & 0xFFFFFF) === 0x0D47A1);
        };

        $this->assertSame(292, $panelTop($image->render($content)));

        $content['more'][] = ['value' => '999.999.999,99', 'unit' => 'kWh pro Kopf', 'label' => 'Zu breit'];
        $this->assertSame(360, $panelTop($image->render($content)));
    }

    public function test_colours_follow_the_branding_and_stay_readable(): void
    {
        $image = app(TileShareImage::class);

        $this->assertSame([
            'surface' => '#ffffff', 'accent' => '#1976d2', 'panel' => '#e30613',
            'panel_text' => '#ffffff', 'title' => '#000000', 'sender' => '#4b5563',
        ], $image->colors(['primary_color' => '#E30613', 'secondary_color' => '#1976d2']));

        // Light primary: white text fails, so the primary text colour is used.
        $this->assertSame('#222222', $image->colors(['primary_color' => '#ffd500', 'text_primary_color' => '#222'])['panel_text']);

        // Dark card surface: black title fails, white is used; a weak secondary falls back to the title colour.
        $dark = $image->colors(['card_background_color' => '#111111', 'text_secondary_color' => '#333333']);
        $this->assertSame('#ffffff', $dark['title']);
        $this->assertSame('#ffffff', $dark['sender']);

        // Accent falls back to the secondary colour, invalid values are ignored.
        $this->assertSame('#0d47a1', $image->colors(['accent_color' => 'rgba(0,0,0,1)'])['panel']);
        $this->assertSame('#abcdef', $image->colors(['accent_color' => null, 'secondary_color' => '#ABCDEF'])['accent']);
    }

    private function png(int $width, int $height): string
    {
        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 0, 0));
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    public function test_replacing_the_logo_file_refreshes_the_preview(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/logo.png', $this->png(100, 50));
        $branding = app(BrandingSettings::class);
        $branding->logo_url = 'branding/logo.png';
        $branding->save();
        $this->tileWithValue(1);
        $before = $this->ogImage('/tiles/co2');

        Storage::disk('public')->put('branding/logo.png', $this->png(200, 50));
        touch(Storage::disk('public')->path('branding/logo.png'), time() + 60);

        $this->assertNotSame($before, $this->ogImage('/tiles/co2'));
    }

    public function test_wide_logos_keep_their_aspect_ratio(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/wide.png', $this->png(1000, 100));

        $gd = imagecreatefromstring(app(TileShareImage::class)->render([
            'layout' => 1, 'locale' => 'de', 'logo' => 'branding/wide.png', 'site_name' => 'Dashboard',
            'colors' => app(TileShareImage::class)->colors([]), 'title' => 'Titel', 'description' => '', 'headline' => null,
        ]));

        // 1000x100 scaled into 360x80 is 360x36, centred in the 80px logo box.
        $red = collect(range(20, 360))->filter(fn ($y) => (imagecolorat($gd, 200, $y) & 0xFFFFFF) === 0xFF0000);
        $this->assertCount(36, $red);
        $this->assertSame(0xFFFFFF, imagecolorat($gd, 64 + 361, $red->first()) & 0xFFFFFF);
    }
}
