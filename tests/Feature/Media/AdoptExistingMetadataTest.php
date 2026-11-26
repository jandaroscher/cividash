<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Alt texts, titles and copyrights editors entered on a field must survive the move into the library. */
class AdoptExistingMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_metadata_survives_adoption_per_locale_and_empty_fields_fall_back_to_the_library(): void
    {
        Cache::flush();
        Storage::fake('public');
        foreach (['text-images/lake.png', 'slider/harbour.png', 'intro/main.png', 'intro/second.png', 'tiles/seo/water.png'] as $path) {
            Storage::disk('public')->put($path, UploadedFile::fake()->image(basename($path))->getContent());
        }
        $tenant = Tenant::where('slug', 'default')->first();

        $blocks = fn (string $locale, string $suffix) => [
            ['type' => 'text-image', 'data' => [
                'heading' => "See {$suffix}",
                'image' => 'text-images/lake.png',
                'image_title' => "Seeufer {$suffix}",
                'image_alt' => "Blick über den See {$suffix}",
                'image_copyright' => "© Stadtarchiv {$suffix}",
            ]],
            ['type' => 'slider', 'data' => ['items' => [
                ['title' => "Hafen {$suffix}", 'image' => 'slider/harbour.png', 'image_alt' => "Boote im Hafen {$suffix}"],
            ]]],
            ['type' => 'intro-text', 'data' => [
                'heading' => "Willkommen {$suffix}",
                'image' => 'intro/main.png',
                'image_alt' => '',
                'image_secondary' => 'intro/second.png',
                'image_secondary_alt' => "Rathaus bei Nacht {$suffix}",
            ]],
        ];
        $page = Page::factory()->forTenant($tenant)->create([
            'title' => ['de' => 'Seeblick', 'en' => 'Lake view'],
            'slug' => ['de' => 'seeblick', 'en' => 'lake-view'],
            'blocks' => ['de' => $blocks('de', 'DE'), 'en' => $blocks('en', 'EN')],
        ]);
        $tile = Tile::create([
            'title' => ['de' => 'Wasser', 'en' => 'Water'],
            'slug' => ['de' => 'wasser', 'en' => 'water'],
            'meta_image' => 'tiles/seo/water.png',
            'tenant_id' => $tenant->id,
        ]);
        $before = $this->metadataIn(DB::table('pages')->where('id', $page->id)->value('blocks'));

        $this->artisan('media:adopt-existing')->assertSuccessful();

        $this->assertSame($before, $this->metadataIn(DB::table('pages')->where('id', $page->id)->value('blocks')));
        $this->assertCount(12, $before, 'fixture covers every metadata field');

        $copy = fn (string $source) => Media::withoutGlobalScopes()->where('source_path', $source)->sole();
        $copy('intro/main.png')->update(['alt' => 'Marktplatz aus der Mediathek']);
        $this->assertIsNumeric(DB::table('tiles')->where('id', $tile->id)->value('meta_image'));

        foreach (['de' => 'DE', 'en' => 'EN'] as $locale => $suffix) {
            $this->getJson("/api/v1/content/pages/{$page->id}?locale={$locale}")->assertOk()
                ->assertJsonPath('blocks.0.props.image', $copy('text-images/lake.png')->path)
                ->assertJsonPath('blocks.0.props.image_title', "Seeufer {$suffix}")
                ->assertJsonPath('blocks.0.props.image_alt', "Blick über den See {$suffix}")
                ->assertJsonPath('blocks.0.props.image_copyright', "© Stadtarchiv {$suffix}")
                ->assertJsonPath('blocks.1.props.items.0.image', $copy('slider/harbour.png')->path)
                ->assertJsonPath('blocks.1.props.items.0.image_alt', "Boote im Hafen {$suffix}")
                ->assertJsonPath('blocks.2.props.image_secondary_alt', "Rathaus bei Nacht {$suffix}")
                ->assertJsonPath('blocks.2.props.image_alt', 'Marktplatz aus der Mediathek');
        }

        $this->getJson("/api/v1/tiles/{$tile->id}?locale=de")->assertOk()
            ->assertJsonPath('data.meta.image', Storage::disk('public')->url($copy('tiles/seo/water.png')->path));
    }

    /** @return array<string, string> every *_title, *_alt and *_copyright value, keyed by its place */
    private function metadataIn(string $json): array
    {
        return array_filter(
            Arr::dot(json_decode($json, true)),
            fn ($value, string $key) => preg_match('/_(title|alt|copyright)$/', $key) === 1,
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
