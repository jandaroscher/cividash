<?php

namespace Tests\Feature\Api;

use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TileBackgroundBlocksApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_tile_with_background_blocks(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'slug' => ['de' => 'test-tile', 'en' => 'test-tile'],
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

        $response = $this->getJson("/api/tiles/{$tile->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'background_blocks' => [
                        '*' => [
                            'type',
                            'props',
                        ],
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertNotNull($data['background_blocks']);
        $this->assertCount(1, $data['background_blocks']);
        $this->assertEquals('hero', $data['background_blocks'][0]['type']);
        $this->assertEquals('Hero Title', $data['background_blocks'][0]['props']['title']);
    }

    public function test_api_returns_tile_with_multiple_background_blocks(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'slug' => ['de' => 'test-tile', 'en' => 'test-tile'],
            'background_blocks' => [
                [
                    'type' => 'hero',
                    'data' => ['title' => 'Hero Title'],
                ],
                [
                    'type' => 'text-image',
                    'data' => ['text' => 'Some text', 'image' => 'image.jpg'],
                ],
            ],
        ]);

        $response = $this->getJson("/api/tiles/{$tile->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data['background_blocks']);
        $this->assertEquals('hero', $data['background_blocks'][0]['type']);
        $this->assertEquals('text-image', $data['background_blocks'][1]['type']);
    }

    public function test_api_filters_inactive_background_blocks(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'slug' => ['de' => 'test-tile', 'en' => 'test-tile'],
            'background_blocks' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'title' => 'Hero Title',
                        'is_active' => true,
                    ],
                ],
                [
                    'type' => 'text-image',
                    'data' => [
                        'text' => 'Hidden text',
                        'is_active' => false,
                    ],
                ],
            ],
        ]);

        $response = $this->getJson("/api/tiles/{$tile->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['background_blocks']);
        $this->assertEquals('hero', $data['background_blocks'][0]['type']);
        $this->assertArrayNotHasKey('is_active', $data['background_blocks'][0]['props']);
    }

    public function test_api_returns_null_when_tile_has_no_background_blocks(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'slug' => ['de' => 'test-tile', 'en' => 'test-tile'],
            'background_blocks' => null,
        ]);

        $response = $this->getJson("/api/tiles/{$tile->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNull($data['background_blocks']);
    }

    public function test_api_handles_blocks_without_data_key(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'slug' => ['de' => 'test-tile', 'en' => 'test-tile'],
            'background_blocks' => [
                [
                    'type' => 'hero',
                    'title' => 'Hero Title',
                    'subtitle' => 'Hero Subtitle',
                ],
            ],
        ]);

        $response = $this->getJson("/api/tiles/{$tile->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotNull($data['background_blocks']);
        $this->assertCount(1, $data['background_blocks']);
        $this->assertEquals('hero', $data['background_blocks'][0]['type']);
        $this->assertArrayHasKey('title', $data['background_blocks'][0]['props']);
        $this->assertEquals('Hero Title', $data['background_blocks'][0]['props']['title']);
    }

    public function test_api_tiles_index_includes_background_blocks(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'slug' => ['de' => 'test-tile', 'en' => 'test-tile'],
            'background_blocks' => [
                ['type' => 'hero', 'data' => ['title' => 'Test']],
            ],
        ]);

        $response = $this->getJson('/api/tiles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'background_blocks',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('background_blocks', $data[0]);
    }

    public function test_api_returns_tile_by_slug_with_locale(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'slug' => ['de' => 'kachel-de', 'en' => 'tile-en'],
        ]);

        $response = $this->getJson('/api/tiles/kachel-de?locale=de');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $tile->id);

        $response = $this->getJson('/api/tiles/tile-en?locale=en');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $tile->id);
    }
}
