<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use Awcodes\Curator\Resources\Media\Pages\CreateMedia;
use Awcodes\Curator\Resources\Media\Pages\ListMedia;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MediaEditPageTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->user = User::factory()->admin()->create(['first_name' => 'Erika', 'last_name' => 'Muster', 'locale' => 'de']);
        $this->actingAs($this->user);
        Filament::setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    private function adopted(): Media
    {
        Storage::disk('public')->put('uploads/lake.png', UploadedFile::fake()->image('lake.png')->getContent());

        return app(MediaLibrary::class)->adopt('uploads/lake.png', $this->tenant->id);
    }

    private function editPage(Media $media): \Illuminate\Testing\TestResponse
    {
        return $this->get("/admin/{$this->tenant->id}/media/{$media->id}/edit")->assertOk();
    }

    public function test_an_upload_on_the_create_page_records_who_uploaded_it(): void
    {
        Livewire::test(CreateMedia::class)
            ->set('data.file', UploadedFile::fake()->image('lake.png'))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($this->user->id, Media::sole()->uploaded_by);
    }

    public function test_a_multi_upload_records_who_uploaded_the_files(): void
    {
        Livewire::test(ListMedia::class)
            ->callAction('curator_multi_upload', ['files' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')]])
            ->assertHasNoActionErrors();

        $this->assertSame([$this->user->id, $this->user->id], Media::pluck('uploaded_by')->all());
    }

    public function test_the_uploader_of_an_adopted_file_is_unknown_and_shown_as_a_dash(): void
    {
        auth()->logout();
        $media = $this->adopted();
        $this->assertNull($media->uploaded_by);

        $this->actingAs($this->user);
        $this->editPage($media)->assertSeeInOrder(['Hochgeladen von', '–']);
    }

    public function test_deleting_the_uploader_keeps_the_file(): void
    {
        $media = $this->adopted();
        $other = User::factory()->create();
        $media->forceFill(['uploaded_by' => $other->id])->saveQuietly();

        $other->delete();

        $this->assertNull($media->fresh()->uploaded_by);
    }

    public function test_the_details_show_upload_date_and_time_and_the_uploader_by_admin_language(): void
    {
        $media = $this->adopted();
        $media->forceFill(['uploaded_by' => $this->user->id, 'created_at' => Carbon::parse('2026-11-26 15:33', 'Europe/Berlin')])->saveQuietly();

        $this->editPage($media)
            ->assertSeeInOrder(['Hochgeladen am', '26.11.2026, 15:33', 'Hochgeladen von', 'Erika Muster', 'Dashboard', 'Test Tenant'])
            ->assertDontSee('Verzeichnis');

        $this->user->update(['locale' => 'en']);
        $this->editPage($media)->assertSee('11/26/2026, 3:33 PM');
    }

    public function test_the_edit_page_header_lists_delete_view_save_like_the_other_edit_pages(): void
    {
        $response = $this->editPage($this->adopted());

        $header = str($response->getContent())->between('fi-header', 'fi-page-content')->toString();
        $this->assertMatchesRegularExpression('/Löschen.*Anzeigen.*Speichern/s', $header);
    }

    public function test_the_preview_is_a_section_without_tabs_or_external_alt_text_link(): void
    {
        $this->editPage($this->adopted())
            ->assertSee('Vorschau')
            ->assertDontSee('role="tablist"', false)
            ->assertDontSee('w3.org/WAI', false);
    }
}
