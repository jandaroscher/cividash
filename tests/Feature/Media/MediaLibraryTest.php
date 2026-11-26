<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Media;

use App\Exceptions\MediaInUseException;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Media;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use Filament\Facades\Filament;
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

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    /** A file uploaded to the library of a dashboard. */
    private function upload(string $path, ?Tenant $tenant = null, array $attributes = []): Media
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image(basename($path), 20, 10)->getContent());

        $media = new Media;
        $media->forceFill([
            'tenant_id' => ($tenant ?? $this->tenant)->id,
            'disk' => 'public',
            'directory' => dirname($path),
            'visibility' => 'public',
            'name' => pathinfo($path, PATHINFO_FILENAME),
            'path' => $path,
            'ext' => pathinfo($path, PATHINFO_EXTENSION),
            'type' => 'image/png',
            'size' => 100,
            ...$attributes,
        ])->saveQuietly();

        return $media;
    }

    private function file(string $path): void
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image(basename($path), 40, 30)->getContent());
    }

    public function test_resolves_an_id_to_its_path_and_passes_a_path_through(): void
    {
        $media = $this->upload('media/1/photo.png');
        $library = app(MediaLibrary::class);

        $this->assertSame('media/1/photo.png', $library->path($media->id));
        $this->assertSame('media/1/photo.png', $library->path((string) $media->id));
        $this->assertSame('text-images/old.png', $library->path('text-images/old.png'));
        $this->assertNull($library->path(null));
        $this->assertNull($library->path(''));
        $this->assertNull($library->path(999999));
        $this->assertSame(Storage::disk('public')->url('media/1/photo.png'), $library->url($media->id));
    }

    public function test_an_id_of_another_dashboard_does_not_resolve(): void
    {
        $other = Tenant::create(['name' => 'Demo City', 'slug' => 'demo-city']);
        $foreign = $this->upload('media/2/foreign.png', $other);
        $this->actingAs(User::factory()->create());
        Filament::setTenant($this->tenant);

        $this->assertNull(app(MediaLibrary::class)->path($foreign->id));
        $this->assertSame(['image' => null], app(MediaLibrary::class)->resolveData(['image' => $foreign->id]));
    }

    public function test_block_data_falls_back_to_library_metadata_unless_the_field_sets_it(): void
    {
        $media = $this->upload('media/1/lake.png', attributes: ['alt' => 'Library alt', 'title' => 'Library title', 'copyright' => 'City archive']);

        $data = app(MediaLibrary::class)->resolveData([
            'image' => $media->id,
            'image_alt' => 'Own alt',
            'items' => [['image' => (string) $media->id]],
            'file' => 'downloads/report.pdf',
        ]);

        $this->assertSame('media/1/lake.png', $data['image']);
        $this->assertSame('Own alt', $data['image_alt']);
        $this->assertSame('Library title', $data['image_title']);
        $this->assertSame('City archive', $data['image_copyright']);
        $this->assertSame('media/1/lake.png', $data['items'][0]['image']);
        $this->assertSame('Library alt', $data['items'][0]['image_alt']);
        $this->assertSame('downloads/report.pdf', $data['file']);
    }

    public function test_tile_api_returns_urls_for_ids_and_legacy_paths_but_not_for_foreign_ids(): void
    {
        $other = Tenant::create(['name' => 'Demo City', 'slug' => 'demo-city']);
        $icon = $this->upload('media/1/icon.png');
        $foreign = $this->upload('media/2/foreign.png', $other);
        $tile = Tile::create([
            'title' => ['de' => 'Wasser', 'en' => 'Water'],
            'slug' => ['de' => 'wasser', 'en' => 'water'],
            'icon' => $icon->id,
            'meta_image' => 'tiles/seo/legacy.png',
            'background_blocks' => ['de' => [
                ['type' => 'text-image', 'data' => ['image' => $icon->id]],
                ['type' => 'text-image', 'data' => ['image' => $foreign->id]],
            ], 'en' => []],
        ]);

        $data = $this->getJson("/api/v1/tiles/{$tile->id}?locale=de")->assertOk()->json('data');

        $this->assertSame(Storage::disk('public')->url('media/1/icon.png'), $data['icon']);
        $this->assertSame(Storage::disk('public')->url('tiles/seo/legacy.png'), $data['meta']['image']);
        $this->assertSame('media/1/icon.png', $data['background_blocks'][0]['props']['image']);
        $this->assertNull($data['background_blocks'][1]['props']['image']);
    }

    public function test_adopting_copies_the_file_once_per_dashboard_and_keeps_the_original(): void
    {
        $other = Tenant::create(['name' => 'Demo City', 'slug' => 'demo-city']);
        $this->file('branding/logo.png');
        $library = app(MediaLibrary::class);

        $first = $library->adopt('/storage/branding/logo.png', $this->tenant->id);
        $again = $library->adopt('branding/logo.png', $this->tenant->id);
        $foreign = $library->adopt('branding/logo.png', $other->id);

        $this->assertTrue($first->is($again));
        $this->assertFalse($first->is($foreign));
        $this->assertStringStartsWith('media/'.$this->tenant->id.'/', $first->path);
        $this->assertStringStartsWith('media/'.$other->id.'/', $foreign->path);
        $this->assertSame('branding/logo.png', $first->source_path);
        $this->assertSame([40, 30], [$first->width, $first->height]);
        Storage::disk('public')->assertExists(['branding/logo.png', $first->path, $foreign->path]);
    }

    public function test_deleting_an_adopted_item_keeps_the_file_that_global_settings_still_use(): void
    {
        $this->file('branding/logo.png');
        DB::table('settings')->updateOrInsert(
            ['group' => 'branding', 'name' => 'logo_url', 'tenant_id' => 0],
            ['payload' => json_encode('branding/logo.png'), 'locked' => false]
        );

        $media = app(MediaLibrary::class)->adopt('branding/logo.png', $this->tenant->id);
        $media->delete();

        $this->assertModelMissing($media);
        Storage::disk('public')->assertMissing($media->path);
        Storage::disk('public')->assertExists('branding/logo.png');
    }

    public function test_a_long_file_name_is_shortened_to_fit_the_path_column(): void
    {
        $name = str_repeat('a', 240).'.png';
        $this->file($name);

        $media = app(MediaLibrary::class)->adopt($name, $this->tenant->id);

        $this->assertLessThanOrEqual(255, strlen($media->path));
        $this->assertStringEndsWith('.png', $media->path);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_converting_paths_to_ids_is_idempotent_and_keeps_missing_files(): void
    {
        $other = Tenant::create(['name' => 'Demo City', 'slug' => 'demo-city']);
        $this->file('text-images/shared.png');

        $page = Page::create([
            'title' => ['de' => 'Start', 'en' => 'Home'],
            'slug' => ['de' => 'start', 'en' => 'home'],
            'layout' => 'default',
            'blocks' => ['de' => [['type' => 'text-image', 'data' => ['image' => 'text-images/shared.png', 'text' => 'Über']]], 'en' => []],
            'meta_image' => 'pages/seo/missing.png',
            'tenant_id' => $this->tenant->id,
        ]);
        $tile = Tile::withoutGlobalScopes()->create([
            'title' => ['de' => 'Luft', 'en' => 'Air'],
            'slug' => ['de' => 'luft', 'en' => 'air'],
            'icon' => 'text-images/shared.png',
            'tenant_id' => $other->id,
        ]);

        $library = app(MediaLibrary::class);
        $stats = $library->convertPathsToIds();
        $library->convertPathsToIds();

        $this->assertSame(['references' => 3, 'adopted' => 2, 'missing' => 1], $stats);
        $this->assertSame(2, Media::withoutGlobalScopes()->count());
        $own = Media::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->sole();
        $copy = Media::withoutGlobalScopes()->where('tenant_id', $other->id)->sole();
        Storage::disk('public')->assertExists(['text-images/shared.png', $own->path, $copy->path]);

        $blocks = json_decode(DB::table('pages')->where('id', $page->id)->value('blocks'), true);
        $this->assertSame($own->id, $blocks['de'][0]['data']['image']);
        $this->assertStringContainsString('Über', DB::table('pages')->where('id', $page->id)->value('blocks'));
        $this->assertSame('pages/seo/missing.png', DB::table('pages')->where('id', $page->id)->value('meta_image'));
        $this->assertEquals($copy->id, DB::table('tiles')->where('id', $tile->id)->value('icon'));

        $library->convertIdsToPaths();
        $this->assertSame($copy->path, DB::table('tiles')->where('id', $tile->id)->value('icon'));
    }

    public function test_settings_of_a_dashboard_are_converted_but_global_defaults_are_not(): void
    {
        $this->file('branding/1/logo.png');
        DB::table('settings')->updateOrInsert(
            ['group' => 'branding', 'name' => 'logo_url', 'tenant_id' => $this->tenant->id],
            ['payload' => json_encode('branding/1/logo.png'), 'locked' => false]
        );
        DB::table('settings')->updateOrInsert(
            ['group' => 'branding', 'name' => 'logo_url', 'tenant_id' => 0],
            ['payload' => json_encode('branding/1/logo.png'), 'locked' => false]
        );

        app(MediaLibrary::class)->convertPathsToIds();

        $media = Media::withoutGlobalScopes()->sole();
        $this->assertSame($media->id, json_decode(DB::table('settings')->where(['name' => 'logo_url', 'tenant_id' => $this->tenant->id])->value('payload')));
        $this->assertSame('branding/1/logo.png', json_decode(DB::table('settings')->where(['name' => 'logo_url', 'tenant_id' => 0])->value('payload')));
    }

    public function test_category_icons_are_converted_per_locale_and_count_as_usages(): void
    {
        $this->file('categories/water.png');
        $group = CategoryGroup::create(['title' => ['de' => 'Themen', 'en' => 'Topics'], 'key' => 'topics', 'tenant_id' => $this->tenant->id]);
        $category = Category::create([
            'category_group_id' => $group->id,
            'slug' => ['de' => 'Wasser', 'en' => 'Water'],
            'key' => 'water',
            'icon' => json_encode(['de' => 'categories/water.png', 'en' => 'categories/water.png']),
            'tenant_id' => $this->tenant->id,
        ]);

        app(MediaLibrary::class)->convertPathsToIds();

        $media = Media::withoutGlobalScopes()->sole();
        $this->assertSame(['de' => $media->id, 'en' => $media->id], json_decode(DB::table('categories')->where('id', $category->id)->value('icon'), true));
        app()->setLocale('de');
        $this->assertSame([['type' => 'categories', 'id' => $category->id, 'label' => 'Kategorie: Wasser']], app(MediaLibrary::class)->usages($media));
    }

    public function test_lists_usages_by_id_and_by_path_and_blocks_deleting_media_in_use(): void
    {
        $byId = $this->upload('media/1/used.png');
        $byPath = $this->upload('media/1/path.png');
        $bySource = app(MediaLibrary::class)->adopt(tap('legacy/old.png', fn ($p) => $this->file($p)), $this->tenant->id);
        $unused = $this->upload('media/1/unused.png');
        Page::create([
            'title' => ['de' => 'Über uns', 'en' => 'About'],
            'slug' => ['de' => 'ueber-uns', 'en' => 'about'],
            'layout' => 'default',
            'blocks' => ['de' => [['type' => 'slider', 'data' => ['items' => [['image' => $byId->id], ['image' => '/storage/media/1/path.png']]]]], 'en' => []],
            'tenant_id' => $this->tenant->id,
        ]);
        DB::table('settings')->updateOrInsert(
            ['group' => 'branding', 'name' => 'logo_url', 'tenant_id' => $this->tenant->id],
            ['payload' => json_encode('legacy/old.png'), 'locked' => false]
        );

        app()->setLocale('de');
        $library = app(MediaLibrary::class);
        $this->assertSame([['type' => 'pages', 'id' => Page::sole()->id, 'label' => 'Seite: Über uns']], $library->usages($byId));
        $this->assertCount(1, $library->usages($byPath));
        $this->assertSame('Einstellungen: Logo', $library->usages($bySource)[0]['label']);
        $this->assertSame([], $library->usages($unused));

        $unused->delete();
        $this->assertModelMissing($unused);

        $this->expectException(MediaInUseException::class);
        $byPath->delete();
    }

    public function test_usages_of_another_dashboard_do_not_count(): void
    {
        $other = Tenant::create(['name' => 'Demo City', 'slug' => 'demo-city']);
        $media = $this->upload('media/1/x.png');
        Tile::withoutGlobalScopes()->create([
            'title' => ['de' => 'Fremd', 'en' => 'Foreign'],
            'slug' => ['de' => 'fremd', 'en' => 'foreign'],
            'icon' => $media->id,
            'tenant_id' => $other->id,
        ]);

        $this->assertSame([], app(MediaLibrary::class)->usages($media));
    }

    public function test_the_admin_api_refuses_ids_of_another_dashboard(): void
    {
        $other = Tenant::create(['name' => 'Demo City', 'slug' => 'demo-city']);
        $own = $this->upload('media/1/own.png');
        $foreign = $this->upload('media/2/foreign.png', $other);
        $user = User::factory()->create();
        $user->tenants()->syncWithoutDetaching([$this->tenant->id]);
        $token = $user->createToken('test', ['admin-api']);
        $token->accessToken->forceFill(['tenant_id' => $this->tenant->id])->save();
        $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];
        $tile = ['title' => ['de' => 'Wasser', 'en' => 'Water'], 'slug' => ['de' => 'wasser', 'en' => 'water']];

        $this->postJson('/api/v1/admin/tiles', [...$tile, 'icon' => (string) $foreign->id], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('icon');
        $this->postJson('/api/v1/admin/tiles', [...$tile, 'background_blocks' => ['de' => [['type' => 'text-image', 'data' => ['image' => $foreign->id]]]]], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('background_blocks');

        $created = $this->postJson('/api/v1/admin/tiles', [...$tile, 'icon' => (string) $own->id], $headers)->assertCreated();
        $this->assertSame('media/1/own.png', $created->json('data.icon'));
    }

    public function test_the_media_route_of_the_library_package_is_not_served(): void
    {
        $this->upload('media/1/photo.png');

        $this->get('/curator/media/1/photo.png?w=100')->assertNotFound();
    }

    public function test_the_admin_api_refuses_foreign_ids_in_localized_category_icons(): void
    {
        $other = Tenant::create(['name' => 'Demo City', 'slug' => 'demo-city']);
        $own = $this->upload('media/1/own.png');
        $foreign = $this->upload('media/2/foreign.png', $other);
        $group = CategoryGroup::create(['title' => ['de' => 'Themen', 'en' => 'Topics'], 'key' => 'topics', 'tenant_id' => $this->tenant->id]);
        $user = User::factory()->create();
        $user->tenants()->syncWithoutDetaching([$this->tenant->id]);
        $token = $user->createToken('test', ['admin-api']);
        $token->accessToken->forceFill(['tenant_id' => $this->tenant->id])->save();
        $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];
        $category = ['category_group_id' => $group->id, 'slug' => ['de' => 'Wasser', 'en' => 'Water'], 'key' => 'water'];

        $this->postJson('/api/v1/admin/categories', [...$category, 'icon' => json_encode(['de' => (string) $foreign->id])], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('icon');
        $this->postJson('/api/v1/admin/categories', [...$category, 'icon' => (string) $foreign->id], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('icon');
        $this->postJson('/api/v1/admin/categories', [...$category, 'icon' => json_encode(['de' => (string) $own->id])], $headers)
            ->assertCreated();
    }
}
