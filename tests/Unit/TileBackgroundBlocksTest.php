<?php

namespace Tests\Unit;

use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TileBackgroundBlocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_tile_can_store_background_blocks(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'background_blocks' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'title' => 'Hero Title',
                        'subtitle' => 'Hero Subtitle',
                    ],
                ],
            ],
        ]);

        $this->assertNotNull($tile->background_blocks);
        $this->assertIsArray($tile->background_blocks);
        $this->assertCount(1, $tile->background_blocks);
        $this->assertEquals('hero', $tile->background_blocks[0]['type']);
    }

    public function test_tile_background_blocks_can_be_null(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'background_blocks' => null,
        ]);

        $this->assertNull($tile->background_blocks);
    }

    public function test_tile_background_blocks_can_store_multiple_blocks(): void
    {
        $blocks = [
            [
                'type' => 'hero',
                'data' => ['title' => 'Hero Title'],
            ],
            [
                'type' => 'text-image',
                'data' => ['text' => 'Some text', 'image' => 'image.jpg'],
            ],
        ];

        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'background_blocks' => $blocks,
        ]);

        $this->assertCount(2, $tile->background_blocks);
        $this->assertEquals('hero', $tile->background_blocks[0]['type']);
        $this->assertEquals('text-image', $tile->background_blocks[1]['type']);
    }

    public function test_tile_background_blocks_are_casted_to_array(): void
    {
        $blocks = [
            ['type' => 'hero', 'data' => ['title' => 'Test']],
        ];

        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'background_blocks' => $blocks,
        ]);

        // Reload from database to ensure casting works
        $tile->refresh();

        $this->assertIsArray($tile->background_blocks);
        $this->assertIsArray($tile->background_blocks[0]);
    }
}
