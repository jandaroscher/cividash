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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdoptExistingCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function tile(string $icon): Tile
    {
        return Tile::create([
            'title' => ['de' => 'Wasser', 'en' => 'Water'],
            'slug' => ['de' => 'wasser', 'en' => 'water'],
            'icon' => $icon,
            'tenant_id' => Tenant::where('slug', 'default')->value('id'),
        ]);
    }

    public function test_moves_referenced_files_into_the_library(): void
    {
        Storage::disk('public')->put('tiles/icons/water.png', UploadedFile::fake()->image('water.png')->getContent());
        $tile = $this->tile('tiles/icons/water.png');

        $this->artisan('media:adopt-existing')
            ->expectsOutputToContain('1 path references, 1 added to the library, 0 files not found')
            ->assertSuccessful();

        $this->assertEquals(Media::withoutGlobalScopes()->sole()->id, DB::table('tiles')->where('id', $tile->id)->value('icon'));
    }

    public function test_warns_when_no_referenced_file_can_be_reached(): void
    {
        Log::spy();
        $tile = $this->tile('tiles/icons/water.png');

        $this->artisan('media:adopt-existing')
            ->expectsOutputToContain('run `php artisan media:adopt-existing` where they are')
            ->assertSuccessful();

        $this->assertSame('tiles/icons/water.png', DB::table('tiles')->where('id', $tile->id)->value('icon'));
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'none of the referenced files were found'));
    }

    public function test_public_page_shows_the_library_copy_right_after_adoption(): void
    {
        Cache::flush();
        Storage::disk('public')->put('text-images/lake.png', UploadedFile::fake()->image('lake.png')->getContent());
        $page = Page::factory()->forTenant(Tenant::where('slug', 'default')->first())->create([
            'title' => ['de' => 'Seeblick', 'en' => 'Lake view'],
            'slug' => ['de' => 'seeblick', 'en' => 'lake-view'],
            'blocks' => ['de' => [['type' => 'text-image', 'data' => ['heading' => 'Am See', 'image' => 'text-images/lake.png']]]],
        ]);
        $this->getJson("/api/v1/content/pages/{$page->id}?locale=de")->assertJsonPath('blocks.0.props.image', 'text-images/lake.png');

        $this->artisan('media:adopt-existing')->assertSuccessful();

        $copy = Media::withoutGlobalScopes()->sole()->path;
        $this->getJson("/api/v1/content/pages/{$page->id}?locale=de")->assertJsonPath('blocks.0.props.image', $copy);
    }
}
