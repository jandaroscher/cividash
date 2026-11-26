<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Media;

use App\Filament\Media\CreateMedia;
use App\Filament\Media\EditMedia;
use App\Filament\Pages\ManageBranding;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\TileResource\Pages\EditTile;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\Theme;
use App\Models\Tile;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use App\Settings\BrandingSettings;
use App\Settings\GeneralSettings;
use Awcodes\Curator\Resources\Media\Pages\ListMedia;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MediaPickerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Tenant $other;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->other = Tenant::create(['name' => 'Demo City', 'slug' => 'demo-city']);
        $this->actingAs(User::factory()->admin()->create());
        Filament::setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    private function media(Tenant $tenant, string $path, int $size = 100): Media
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image(basename($path))->getContent());
        $media = app(MediaLibrary::class)->adopt($path, $tenant->id);
        $media->forceFill(['size' => $size])->saveQuietly();

        return $media;
    }

    private function tile(array $attributes): Tile
    {
        return Tile::create([
            'title' => ['de' => 'Wasser', 'en' => 'Water'],
            'slug' => ['de' => 'wasser', 'en' => 'water'],
            'tenant_id' => $this->tenant->id,
            ...$attributes,
        ]);
    }

    public function test_picking_a_logo_stores_its_id_and_the_api_returns_its_url(): void
    {
        $logo = $this->media($this->tenant, 'uploads/logo.png');

        Livewire::test(ManageBranding::class)
            ->set('data.logo_url', [(string) Str::uuid() => $logo->toArray()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals($logo->id, app(BrandingSettings::class)->logo_url);
        $this->assertSame(Storage::disk('public')->url($logo->path), app(MediaLibrary::class)->url(app(BrandingSettings::class)->logo_url));
    }

    public function test_a_logo_larger_than_the_field_allows_is_refused(): void
    {
        // The logo field takes 2 MB; the library itself takes larger files.
        $logo = $this->media($this->tenant, 'uploads/large-logo.png', 2049 * 1024);

        Livewire::test(ManageBranding::class)
            ->set('data.logo_url', [(string) Str::uuid() => $logo->toArray()])
            ->call('save')
            ->assertHasFormErrors(['logo_url']);
    }

    public function test_media_of_another_dashboard_cannot_be_picked(): void
    {
        $foreign = $this->media($this->other, 'uploads/foreign.png');

        Livewire::test(ManageBranding::class)
            ->set('data.logo_url', [(string) Str::uuid() => $foreign->toArray()])
            ->call('save')
            ->assertHasFormErrors(['logo_url']);
    }

    public function test_a_stored_path_is_copied_into_the_library_once_and_saved_as_its_id(): void
    {
        Storage::disk('public')->put('tiles/seo/old.png', UploadedFile::fake()->image('old.png')->getContent());
        $tile = $this->tile(['meta_image' => 'tiles/seo/old.png']);

        Livewire::test(EditTile::class, ['record' => $tile->getRouteKey()]);
        Livewire::test(EditTile::class, ['record' => $tile->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $media = Media::sole();
        $this->assertSame('tiles/seo/old.png', $media->source_path);
        $this->assertEquals($media->id, DB::table('tiles')->where('id', $tile->id)->value('meta_image'));
        Storage::disk('public')->assertExists(['tiles/seo/old.png', $media->path]);
    }

    public function test_a_stored_path_whose_file_is_missing_survives_saving(): void
    {
        $tile = $this->tile(['meta_image' => 'tiles/seo/gone.png']);

        Livewire::test(EditTile::class, ['record' => $tile->getRouteKey()])
            ->assertOk()
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('tiles/seo/gone.png', DB::table('tiles')->where('id', $tile->id)->value('meta_image'));
        $this->assertSame(0, Media::count());
    }

    public function test_a_category_icon_is_picked_per_locale(): void
    {
        $icon = $this->media($this->tenant, 'uploads/water.png');
        $group = CategoryGroup::create(['title' => ['de' => 'Themen', 'en' => 'Topics'], 'key' => 'topics', 'tenant_id' => $this->tenant->id]);
        $category = Category::create([
            'category_group_id' => $group->id,
            'slug' => ['de' => 'Wasser', 'en' => 'Water'],
            'key' => 'water',
            'icon' => json_encode(['en' => 'categories/old-en.png']),
            'tenant_id' => $this->tenant->id,
        ]);
        app()->setLocale('de');

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->set('data.icon', [(string) Str::uuid() => $icon->toArray()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['en' => 'categories/old-en.png', 'de' => $icon->id], json_decode(DB::table('categories')->where('id', $category->id)->value('icon'), true));
    }

    public function test_media_library_lists_only_the_current_dashboard(): void
    {
        $own = $this->media($this->tenant, 'uploads/own.png');
        $foreign = $this->media($this->other, 'uploads/foreign.png');
        // Livewire tests skip the panel middleware that applies the tenant scope.
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::getPanel('admin')->bootTenancy();

        Livewire::test(ListMedia::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_media_of_another_dashboard_cannot_be_opened(): void
    {
        $foreign = $this->media($this->other, 'uploads/foreign.png');

        $this->get("/admin/{$this->tenant->id}/media/{$foreign->id}/edit")->assertNotFound();
    }

    public function test_deleting_media_in_use_is_refused_with_a_message(): void
    {
        $media = $this->media($this->tenant, 'uploads/used.png');
        $this->tile(['icon' => $media->id]);

        Livewire::test(EditMedia::class, ['record' => $media->getKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified(__('filament.media.in_use'));

        $this->assertModelExists($media);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_an_upload_lands_in_the_folder_of_the_current_dashboard(): void
    {
        Livewire::test(CreateMedia::class)
            ->set('data.file', UploadedFile::fake()->image('lake.png', 64, 48))
            ->set('data.alt.de', 'A lake')
            ->call('create')
            ->assertHasNoFormErrors();

        $media = Media::sole();
        $this->assertSame($this->tenant->id, $media->tenant_id);
        $this->assertStringStartsWith('media/'.$this->tenant->id.'/', $media->path);
        $this->assertSame([64, 48], [$media->width, $media->height]);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_the_library_refuses_files_no_content_field_accepts(): void
    {
        Livewire::test(CreateMedia::class)
            ->set('data.file', UploadedFile::fake()->create('notes.txt', 1, 'text/plain'))
            ->call('create')
            ->assertHasFormErrors(['file']);

        $this->assertSame(0, Media::count());
    }

    public function test_an_uploaded_svg_is_sanitized_on_disk(): void
    {
        // Category icons and other fields take SVGs, which now arrive through the library.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(document.cookie)</script><circle r="1"/></svg>';

        Livewire::test(CreateMedia::class)
            ->set('data.file', UploadedFile::fake()->createWithContent('malicious.svg', $svg))
            ->call('create')
            ->assertHasNoFormErrors();

        $stored = Storage::disk('public')->get(Media::sole()->path);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
        $this->assertStringNotContainsString('alert', $stored);
        $this->assertStringContainsString('<circle', $stored);
    }

    public function test_saving_the_theme_page_keeps_inherited_logo_and_favicon_as_paths(): void
    {
        Storage::disk('public')->put('themes/demo/logo.png', UploadedFile::fake()->image('logo.png')->getContent());
        Storage::disk('public')->put('branding/favicon.png', UploadedFile::fake()->image('favicon.png')->getContent());
        $theme = Theme::create(['name' => 'Demo', 'slug' => 'demo', 'settings' => ['branding' => ['logo_url' => 'themes/demo/logo.png']]]);
        $this->tenant->update(['theme_id' => $theme->id]);
        DB::table('settings')->updateOrInsert(
            ['group' => 'general', 'name' => 'favicon', 'tenant_id' => 0],
            ['payload' => json_encode('branding/favicon.png'), 'locked' => false]
        );
        DB::table('settings')->where('tenant_id', $this->tenant->id)->whereIn('name', ['logo_url', 'favicon'])->delete();

        Livewire::test(ManageBranding::class)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, Media::withoutGlobalScopes()->count());
        $this->assertSame('themes/demo/logo.png', app(BrandingSettings::class)->logo_url);
        $this->assertSame('branding/favicon.png', app(GeneralSettings::class)->favicon);
    }

    public function test_an_older_file_larger_than_the_field_allows_does_not_block_saving(): void
    {
        $large = $this->media($this->tenant, 'uploads/large.png', 6 * 1024 * 1024);
        $tile = $this->tile(['meta_image' => $large->id]);

        Livewire::test(EditTile::class, ['record' => $tile->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals($large->id, DB::table('tiles')->where('id', $tile->id)->value('meta_image'));
    }

    public function test_the_picker_edits_alt_text_and_title_per_language_like_the_library(): void
    {
        $media = $this->media($this->tenant, 'uploads/lake.png');
        $media->setTranslations('alt', ['de' => 'Ein See'])->saveQuietly();

        Livewire::test('curator-panel')
            ->mountAction('editItem', ['item' => ['id' => $media->id]])
            ->assertSchemaStateSet(['alt.de' => 'Ein See', 'alt.en' => null], 'mountedActionSchema0')
            // The package's single-value alt field carries the w3.org hint; ours has none and notes the fallback instead
            ->assertSchemaComponentDoesNotExist('alt', 'mountedActionSchema0')
            ->assertSchemaComponentExists('alt.de', 'mountedActionSchema0', fn (TextInput $field): bool => ! str_contains($field->toHtml(), 'w3.org/WAI'))
            ->assertSchemaComponentExists('alt.en', 'mountedActionSchema0', fn (TextInput $field): bool => str_contains($field->toHtml(), e(__('filament.media.uses_default_locale', ['locale' => 'DE']))))
            ->fillForm(['alt.en' => 'A lake', 'title.en' => 'Lake'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $media->refresh();
        $this->assertSame(['de' => 'Ein See', 'en' => 'A lake'], $media->getTranslations('alt'));
        $this->assertSame('Lake', $media->getTranslation('title', 'en', false));
    }

    public function test_searching_the_picker_and_the_library_matches_texts_not_their_stored_language_keys(): void
    {
        $media = $this->media($this->tenant, 'uploads/lake.png');
        $media->setTranslations('alt', ['de' => 'Ein See', 'en' => 'A lake'])->setTranslations('title', ['de' => 'Seeblick', 'en' => 'Lake view'])->forceFill(['name' => 'lake'])->saveQuietly();
        $panel = Livewire::test('curator-panel');
        $found = fn (string $search) => collect($panel->set('search', $search)->get('files'))->pluck('id')->all();

        foreach (['de', 'en', '"', ':'] as $search) {
            $this->assertSame([], $found($search), "Searching for {$search}");
        }
        // The table reads a lone quote as an empty phrase search, so it lists everything for that one
        foreach (['de', 'en', ':'] as $search) {
            Livewire::test(ListMedia::class)->searchTable($search)->assertCanNotSeeTableRecords([$media]);
        }
        $this->assertSame([$media->id], $found('view'));
        $this->assertSame([$media->id], $found('Seeblick'));
        Livewire::test(ListMedia::class)->searchTable('view')->assertCanSeeTableRecords([$media]);
    }
}
