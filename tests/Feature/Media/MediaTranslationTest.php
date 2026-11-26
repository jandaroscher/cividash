<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\Page;
use App\Models\Tenant;
use App\Services\Media\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Alt text and title of library items per language, and how blocks fall back to them. */
class MediaTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->tenant = Tenant::where('slug', 'default')->firstOrFail();
    }

    /** @param  array<string, string>  $alt */
    private function media(array $alt): Media
    {
        $path = 'uploads/lake-'.uniqid().'.png';
        Storage::disk('public')->put($path, UploadedFile::fake()->image('lake.png')->getContent());
        $media = app(MediaLibrary::class)->adopt($path, $this->tenant->id);
        $media->setTranslations('alt', $alt)->saveQuietly();

        return $media;
    }

    private function alt(Media $media, string $locale, ?string $own = null): ?string
    {
        return app(MediaLibrary::class)->resolveData(['image' => $media->id, 'image_alt' => $own], $locale)['image_alt'];
    }

    public function test_a_block_text_wins_in_every_language(): void
    {
        $media = $this->media(['de' => 'Ein See', 'en' => 'A lake']);

        $this->assertSame('Eigener Text', $this->alt($media, 'de', 'Eigener Text'));
        $this->assertSame('Own text', $this->alt($media, 'en', 'Own text'));
    }

    public function test_an_empty_block_text_takes_the_library_text_of_the_same_language(): void
    {
        $media = $this->media(['de' => 'Ein See', 'en' => 'A lake']);

        $this->assertSame('Ein See', $this->alt($media, 'de'));
        $this->assertSame('A lake', $this->alt($media, 'en'));
    }

    public function test_without_a_library_text_in_that_language_the_default_language_is_used(): void
    {
        $this->assertSame('Ein See', $this->alt($this->media(['de' => 'Ein See']), 'en'));
        $this->assertNull($this->alt($this->media(['en' => 'A lake']), 'de'), 'English never stands in for the default language');
    }

    public function test_an_english_page_shows_the_german_library_alt_text_until_an_english_one_exists(): void
    {
        $media = $this->media(['de' => 'Ein See']);
        $page = Page::factory()->forTenant($this->tenant)->create([
            'is_public' => true,
            'blocks' => ['de' => [['type' => 'text-image', 'data' => ['heading' => 'Am See', 'image' => $media->id]]], 'en' => [['type' => 'text-image', 'data' => ['heading' => 'At the lake', 'image' => $media->id]]]],
        ]);

        $this->getJson("/api/v1/content/pages/{$page->id}?locale=en")->assertJsonPath('blocks.0.props.image_alt', 'Ein See');

        // Saving the library item clears the cached page responses that show its texts
        $media->setTranslation('alt', 'en', 'A lake')->save();
        $this->app->forgetScopedInstances(); // each real request starts with a fresh media lookup

        $this->getJson("/api/v1/content/pages/{$page->id}?locale=en")->assertJsonPath('blocks.0.props.image_alt', 'A lake');
        $this->getJson("/api/v1/content/pages/{$page->id}?locale=de")->assertJsonPath('blocks.0.props.image_alt', 'Ein See');
        $this->getJson("/api/v1/content/pages/{$page->id}")
            ->assertJsonPath('de.blocks.0.props.image_alt', 'Ein See')
            ->assertJsonPath('en.blocks.0.props.image_alt', 'A lake');
    }

    public function test_the_migration_moves_existing_texts_into_the_default_language_and_back(): void
    {
        $migration = require database_path('migrations/2026_11_27_100001_translate_media_alt_and_title.php');
        $id = DB::table('curator')->insertGetId([
            'tenant_id' => $this->tenant->id, 'disk' => 'public', 'name' => 'lake', 'path' => 'media/1/lake.png',
            'type' => 'image/png', 'ext' => 'png', 'alt' => 'Ein See im Abendlicht', 'title' => 'Seeblick', 'copyright' => 'Stadtwerke',
        ]);
        $row = fn () => DB::table('curator')->find($id);

        $migration->up();
        $migration->up();
        $this->assertSame(['de' => 'Ein See im Abendlicht'], json_decode($row()->alt, true));
        $this->assertSame(['de' => 'Seeblick'], json_decode($row()->title, true));
        $this->assertSame('Stadtwerke', $row()->copyright);

        $migration->down();
        $this->assertSame('Ein See im Abendlicht', $row()->alt);
        $this->assertSame('Seeblick', $row()->title);

        $migration->up();
    }
}
