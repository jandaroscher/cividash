<?php

namespace Tests\Unit;

use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TileBackgroundBlocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_tile_can_store_translatable_background_blocks(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'background_blocks' => [
                'de' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Hero Title',
                            'subtitle' => 'Hero Subtitle',
                        ],
                    ],
                ],
                'en' => [],
            ],
        ]);

        $deBlocks = $tile->getTranslation('background_blocks', 'de');
        $this->assertNotNull($deBlocks);
        $this->assertIsArray($deBlocks);
        $this->assertCount(1, $deBlocks);
        $this->assertEquals('hero', $deBlocks[0]['type']);
    }

    public function test_tile_background_blocks_can_be_empty_per_locale(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'background_blocks' => [
                'de' => [],
                'en' => [],
            ],
        ]);

        $this->assertEmpty($tile->getTranslation('background_blocks', 'de'));
        $this->assertEmpty($tile->getTranslation('background_blocks', 'en'));
    }

    public function test_tile_background_blocks_can_store_multiple_blocks_per_locale(): void
    {
        $deBlocks = [
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
            'background_blocks' => [
                'de' => $deBlocks,
                'en' => [],
            ],
        ]);

        $blocks = $tile->getTranslation('background_blocks', 'de');
        $this->assertCount(2, $blocks);
        $this->assertEquals('hero', $blocks[0]['type']);
        $this->assertEquals('text-image', $blocks[1]['type']);
    }

    public function test_tile_background_blocks_are_casted_to_array(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'background_blocks' => [
                'de' => [
                    ['type' => 'hero', 'data' => ['title' => 'Test']],
                ],
                'en' => [],
            ],
        ]);

        // Reload from database to ensure casting works
        $tile->refresh();

        $blocks = $tile->getTranslation('background_blocks', 'de');
        $this->assertIsArray($blocks);
        $this->assertIsArray($blocks[0]);
    }

    public function test_tile_background_blocks_supports_different_content_per_locale(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'background_blocks' => [
                'de' => [
                    ['type' => 'intro-text', 'data' => ['heading' => 'Deutsche Ueberschrift']],
                ],
                'en' => [
                    ['type' => 'intro-text', 'data' => ['heading' => 'English Heading']],
                ],
            ],
        ]);

        $tile->refresh();

        $deBlocks = $tile->getTranslation('background_blocks', 'de');
        $enBlocks = $tile->getTranslation('background_blocks', 'en');

        $this->assertEquals('Deutsche Ueberschrift', $deBlocks[0]['data']['heading']);
        $this->assertEquals('English Heading', $enBlocks[0]['data']['heading']);
    }
}
