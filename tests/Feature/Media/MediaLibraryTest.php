<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Media;

use App\Exceptions\MediaInUseException;
use App\Models\Media;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\Tile;
use App\Services\Media\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->tenant = Tenant::where('slug', 'default')->firstOrFail();
    }

    private function media(string $path, ?Tenant $tenant = null, array $attributes = []): Media
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image(basename($path), 20, 10)->getContent());

        $media = app(MediaLibrary::class)->adopt($path, ($tenant ?? $this->tenant)->id);
        $media->forceFill($attributes)->saveQuietly();

        return $media;
    }

    public function test_resolves_an_id_to_its_path_and_passes_a_path_through(): void
    {
        $media = $this->media('media/1/photo.jpg');
        $library = app(MediaLibrary::class);

        $this->assertSame('media/1/photo.jpg', $library->path($media->id));
        $this->assertSame('media/1/photo.jpg', $library->path((string) $media->id));
        $this->assertSame('text-images/old.png', $library->path('text-images/old.png'));
        $this->assertNull($library->path(null));
        $this->assertNull($library->path(''));
        $this->assertNull($library->path(999999));
        $this->assertSame(Storage::disk('public')->url('media/1/photo.jpg'), $library->url($media->id));
    }

    public function test_block_data_falls_back_to_library_metadata_unless_the_field_sets_it(): void
    {
        $media = $this->media('media/1/lake.jpg', attributes: ['alt' => 'Library alt', 'title' => 'Library title', 'copyright' => 'City archive']);

        $data = app(MediaLibrary::class)->resolveData([
            'image' => $media->id,
            'image_alt' => 'Own alt',
            'items' => [['image' => (string) $media->id]],
            'file' => 'downloads/report.pdf',
        ]);

        $this->assertSame('media/1/lake.jpg', $data['image']);
        $this->assertSame('Own alt', $data['image_alt']);
        $this->assertSame('Library title', $data['image_title']);
        $this->assertSame('City archive', $data['image_copyright']);
        $this->assertSame('media/1/lake.jpg', $data['items'][0]['image']);
        $this->assertSame('Library alt', $data['items'][0]['image_alt']);
        $this->assertSame('downloads/report.pdf', $data['file']);
    }

    public function test_tile_api_keeps_returning_urls_for_ids_and_legacy_paths(): void
    {
        $icon = $this->media('media/1/icon.png');
        $tile = Tile::create([
            'title' => ['de' => 'Wasser', 'en' => 'Water'],
            'slug' => ['de' => 'wasser', 'en' => 'water'],
            'icon' => $icon->id,
            'meta_image' => 'tiles/seo/legacy.png',
            'background_blocks' => ['de' => [['type' => 'text-image', 'data' => ['image' => $icon->id]]], 'en' => []],
        ]);

        $data = $this->getJson("/api/v1/tiles/{$tile->id}?locale=de")->assertOk()->json('data');

        $this->assertSame(Storage::disk('public')->url('media/1/icon.png'), $data['icon']);
        $this->assertSame(Storage::disk('public')->url('tiles/seo/legacy.png'), $data['meta']['image']);
        $this->assertSame('media/1/icon.png', $data['background_blocks'][0]['props']['image']);
    }

    public function test_converting_paths_to_ids_is_idempotent_and_keeps_missing_files(): void
    {
        $other = Tenant::create(['name' => 'Demo City', 'slug' => 'demo-city']);
        Storage::disk('public')->put('text-images/shared.jpg', UploadedFile::fake()->image('shared.jpg', 40, 30)->getContent());

        $page = Page::create([
            'title' => ['de' => 'Start', 'en' => 'Home'],
            'slug' => ['de' => 'start', 'en' => 'home'],
            'layout' => 'default',
            'blocks' => ['de' => [['type' => 'text-image', 'data' => ['image' => 'text-images/shared.jpg']]], 'en' => []],
            'meta_image' => 'pages/seo/missing.png',
            'tenant_id' => $this->tenant->id,
        ]);
        $tile = Tile::create([
            'title' => ['de' => 'Luft', 'en' => 'Air'],
            'slug' => ['de' => 'luft', 'en' => 'air'],
            'icon' => 'text-images/shared.jpg',
            'tenant_id' => $other->id,
        ]);

        $library = app(MediaLibrary::class);
        $library->convertPathsToIds();
        $library->convertPathsToIds();

        $this->assertSame(2, Media::count());
        $own = Media::where('tenant_id', $this->tenant->id)->sole();
        $copy = Media::where('tenant_id', $other->id)->sole();

        $this->assertSame('text-images/shared.jpg', $own->path);
        $this->assertSame([40, 30, 'image/jpeg'], [$own->width, $own->height, $own->type]);
        $this->assertNotSame($own->path, $copy->path);
        Storage::disk('public')->assertExists($copy->path);

        $blocks = json_decode(DB::table('pages')->where('id', $page->id)->value('blocks'), true);
        $this->assertSame($own->id, $blocks['de'][0]['data']['image']);
        $this->assertSame('pages/seo/missing.png', DB::table('pages')->where('id', $page->id)->value('meta_image'));
        $this->assertEquals($copy->id, DB::table('tiles')->where('id', $tile->id)->value('icon'));

        $library->convertIdsToPaths();
        $this->assertSame($copy->path, DB::table('tiles')->where('id', $tile->id)->value('icon'));
    }

    public function test_settings_of_a_dashboard_are_converted_but_global_defaults_are_not(): void
    {
        Storage::disk('public')->put('branding/1/logo.png', UploadedFile::fake()->image('logo.png')->getContent());
        DB::table('settings')->updateOrInsert(
            ['group' => 'branding', 'name' => 'logo_url', 'tenant_id' => $this->tenant->id],
            ['payload' => json_encode('branding/1/logo.png'), 'locked' => false]
        );
        DB::table('settings')->updateOrInsert(
            ['group' => 'branding', 'name' => 'logo_url', 'tenant_id' => 0],
            ['payload' => json_encode('branding/1/logo.png'), 'locked' => false]
        );

        app(MediaLibrary::class)->convertPathsToIds();

        $media = Media::sole();
        $this->assertSame($media->id, json_decode(DB::table('settings')->where(['name' => 'logo_url', 'tenant_id' => $this->tenant->id])->value('payload')));
        $this->assertSame('branding/1/logo.png', json_decode(DB::table('settings')->where(['name' => 'logo_url', 'tenant_id' => 0])->value('payload')));
    }

    public function test_lists_usages_and_blocks_deleting_media_in_use(): void
    {
        $used = $this->media('media/1/used.jpg');
        $unused = $this->media('media/1/unused.jpg');
        Page::create([
            'title' => ['de' => 'Über uns', 'en' => 'About'],
            'slug' => ['de' => 'ueber-uns', 'en' => 'about'],
            'layout' => 'default',
            'blocks' => ['de' => [['type' => 'slider', 'data' => ['items' => [['image' => $used->id]]]]], 'en' => []],
            'tenant_id' => $this->tenant->id,
        ]);

        app()->setLocale('de');
        $usages = app(MediaLibrary::class)->usages($used);
        $this->assertCount(1, $usages);
        $this->assertSame('pages', $usages[0]['type']);
        $this->assertSame('Seite: Über uns', $usages[0]['label']);
        $this->assertSame([], app(MediaLibrary::class)->usages($unused));

        $unused->delete();
        $this->assertModelMissing($unused);

        $this->expectException(MediaInUseException::class);
        $used->delete();
    }

    public function test_usages_of_another_dashboard_do_not_count(): void
    {
        $other = Tenant::create(['name' => 'Demo City', 'slug' => 'demo-city']);
        $media = $this->media('media/1/x.jpg');
        Tile::create([
            'title' => ['de' => 'Fremd', 'en' => 'Foreign'],
            'slug' => ['de' => 'fremd', 'en' => 'foreign'],
            'icon' => $media->id,
            'tenant_id' => $other->id,
        ]);

        $this->assertSame([], app(MediaLibrary::class)->usages($media));
    }
}
