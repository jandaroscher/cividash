<?php

namespace Tests\Feature;

use App\Filament\Fabricator\Layouts\LandingpageLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Page;

class TileAppBlockRenderingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a page with TileAppBlock renders the Vue mount element
     * with the expected data attributes.
     */
    public function test_tile_app_block_renders_vue_mountpoint(): void
    {
        $page = Page::create([
            'title' => ['de' => 'Tile App Test Page', 'en' => ''],
            'slug' => ['de' => 'tile-app-test', 'en' => ''],
            'layout' => LandingpageLayout::getName(),
            'blocks' => [
                'de' => [
                    [
                        'type' => 'tile-app',
                        'data' => [
                            'mode' => 'explore',
                            'initial_category' => 'energy',
                            'use_mock_data' => true,
                        ],
                    ],
                ],
                'en' => [],
            ],
        ]);

        $response = $this->get('/tile-app-test');

        $response->assertStatus(200);

        // Mount element with data-vue-component
        $response->assertSee('data-vue-component="TileExplorer"', false);

        // data-props JSON should contain at least the mode and initialCategory keys
        $response->assertSee('data-props=', false);
        $response->assertSee('mode', false);
        $response->assertSee('initialCategory', false);
    }
}


