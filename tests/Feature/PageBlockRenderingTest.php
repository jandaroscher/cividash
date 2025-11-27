<?php

namespace Tests\Feature;

use App\Filament\Fabricator\Layouts\LandingpageLayout;
use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\Page;

class PageBlockRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create fake storage disk for testing
        Storage::fake('public');
        
        // Set default locale for translations
        app()->setLocale('de');
    }

    /**
     * Test that CardGridBlock renders all tiles when none are selected.
     */
    public function test_card_grid_block_renders_all_tiles_when_none_selected(): void
    {
        // Create test tiles
        $tile1 = Tile::create([
            'title' => ['de' => 'Tile 1', 'en' => 'Tile 1'],
            'description' => ['de' => 'Description 1', 'en' => 'Description 1'],
            'position' => 1,
        ]);

        $tile2 = Tile::create([
            'title' => ['de' => 'Tile 2', 'en' => 'Tile 2'],
            'description' => ['de' => 'Description 2', 'en' => 'Description 2'],
            'position' => 2,
        ]);

        // Create a page with CardGridBlock (no tiles selected)
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-card-grid',
            'layout' => LandingpageLayout::getName(),
            'blocks' => [
                [
                    'type' => 'card-grid',
                    'data' => [
                        'tiles' => [], // Empty = all tiles
                    ],
                ],
            ],
        ]);

        // Render the page
        $response = $this->get('/test-card-grid');

        // Assert page renders successfully
        $response->assertStatus(200);
        
        // Debug: Check if block is rendered at all
        $content = $response->getContent();
        
        // Check if "Keine Tiles verfügbar" is shown (means block rendered but no tiles)
        // or if tiles are shown
        if (str_contains($content, 'Keine Tiles verfügbar')) {
            $this->fail('CardGridBlock rendered but found no tiles. Content: ' . substr($content, 0, 500));
        }
        
        // Assert both tiles are rendered (check for tile titles)
        $response->assertSee('Tile 1', false);
        $response->assertSee('Tile 2', false);
    }

    /**
     * Test that CardGridBlock renders only selected tiles.
     */
    public function test_card_grid_block_renders_only_selected_tiles(): void
    {
        // Create test tiles
        $tile1 = Tile::create([
            'title' => ['de' => 'Tile 1', 'en' => 'Tile 1'],
            'description' => ['de' => 'Description 1', 'en' => 'Description 1'],
            'position' => 1,
        ]);

        $tile2 = Tile::create([
            'title' => ['de' => 'Tile 2', 'en' => 'Tile 2'],
            'description' => ['de' => 'Description 2', 'en' => 'Description 2'],
            'position' => 2,
        ]);

        $tile3 = Tile::create([
            'title' => ['de' => 'Tile 3', 'en' => 'Tile 3'],
            'description' => ['de' => 'Description 3', 'en' => 'Description 3'],
            'position' => 3,
        ]);

        // Create a page with CardGridBlock (only tile1 and tile3 selected)
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-card-grid-selected',
            'layout' => LandingpageLayout::getName(),
            'blocks' => [
                [
                    'type' => 'card-grid',
                    'data' => [
                        'tiles' => [$tile1->id, $tile3->id],
                    ],
                ],
            ],
        ]);

        // Render the page
        $response = $this->get('/test-card-grid-selected');

        // Assert page renders successfully
        $response->assertStatus(200);
        
        // Assert selected tiles are rendered
        $response->assertSee('Tile 1', false);
        $response->assertSee('Tile 3', false);
        
        // Assert non-selected tile is NOT rendered
        $response->assertDontSee('Tile 2', false);
    }
}

