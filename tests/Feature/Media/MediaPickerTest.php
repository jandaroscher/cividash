<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Media;

use App\Filament\Pages\ManageBranding;
use App\Filament\Resources\TileResource\Pages\EditTile;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use App\Settings\BrandingSettings;
use Awcodes\Curator\Resources\Media\Pages\CreateMedia;
use Awcodes\Curator\Resources\Media\Pages\EditMedia;
use Awcodes\Curator\Resources\Media\Pages\ListMedia;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
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

    private function media(Tenant $tenant, string $path): Media
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image(basename($path))->getContent());

        return app(MediaLibrary::class)->adopt($path, $tenant->id);
    }

    public function test_picking_a_logo_stores_its_id_and_the_api_returns_its_url(): void
    {
        $logo = $this->media($this->tenant, 'media/1/logo.png');

        Livewire::test(ManageBranding::class)
            ->set('data.logo_url', [(string) Str::uuid() => $logo->toArray()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals($logo->id, app(BrandingSettings::class)->logo_url);
        $this->assertSame(Storage::disk('public')->url('media/1/logo.png'), app(MediaLibrary::class)->url(app(BrandingSettings::class)->logo_url));
    }

    public function test_media_of_another_dashboard_cannot_be_picked(): void
    {
        $foreign = $this->media($this->other, 'media/2/foreign.png');

        Livewire::test(ManageBranding::class)
            ->set('data.logo_url', [(string) Str::uuid() => $foreign->toArray()])
            ->call('save')
            ->assertHasFormErrors(['logo_url']);
    }

    public function test_a_stored_path_is_added_to_the_library_when_the_form_loads(): void
    {
        Storage::disk('public')->put('tiles/seo/old.png', UploadedFile::fake()->image('old.png')->getContent());
        $tile = Tile::create([
            'title' => ['de' => 'Wasser', 'en' => 'Water'],
            'slug' => ['de' => 'wasser', 'en' => 'water'],
            'meta_image' => 'tiles/seo/old.png',
            'tenant_id' => $this->tenant->id,
        ]);

        Livewire::test(EditTile::class, ['record' => $tile->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $media = Media::where('tenant_id', $this->tenant->id)->sole();
        $this->assertSame('tiles/seo/old.png', $media->path);
        $this->assertEquals($media->id, DB::table('tiles')->where('id', $tile->id)->value('meta_image'));
    }

    public function test_media_library_lists_only_the_current_dashboard(): void
    {
        $own = $this->media($this->tenant, 'media/1/own.png');
        $foreign = $this->media($this->other, 'media/2/foreign.png');
        // Livewire tests skip the panel middleware that applies the tenant scope.
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::getPanel('admin')->bootTenancy();

        Livewire::test(ListMedia::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_deleting_media_in_use_is_refused_with_a_message(): void
    {
        $media = $this->media($this->tenant, 'media/1/used.png');
        Tile::create([
            'title' => ['de' => 'Luft', 'en' => 'Air'],
            'slug' => ['de' => 'luft', 'en' => 'air'],
            'icon' => $media->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Livewire::test(EditMedia::class, ['record' => $media->getKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified(__('filament.media.in_use'));

        $this->assertModelExists($media);
        Storage::disk('public')->assertExists('media/1/used.png');
    }

    public function test_an_upload_lands_in_the_folder_of_the_current_dashboard(): void
    {
        Livewire::test(CreateMedia::class)
            ->set('data.file', UploadedFile::fake()->image('lake.png', 64, 48))
            ->set('data.alt', 'A lake')
            ->call('create')
            ->assertHasNoFormErrors();

        $media = Media::sole();
        $this->assertSame($this->tenant->id, $media->tenant_id);
        $this->assertStringStartsWith('media/'.$this->tenant->id.'/', $media->path);
        $this->assertSame([64, 48], [$media->width, $media->height]);
        Storage::disk('public')->assertExists($media->path);
    }
}
