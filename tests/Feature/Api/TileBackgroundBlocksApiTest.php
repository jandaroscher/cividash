<?php

namespace Tests\Feature\Api;

use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TileBackgroundBlocksApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_tile_with_background_blocks_for_locale(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'slug' => ['de' => 'test-tile', 'en' => 'test-tile'],
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

        $response = $this->getJson("/api/tiles/{$tile->id}?locale=de");

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

    public function test_api_returns_all_locale_blocks_without_locale_param(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'slug' => ['de' => 'test-tile', 'en' => 'test-tile'],
            'background_blocks' => [
                'de' => [
                    [
                        'type' => 'hero',
                        'data' => ['title' => 'Held'],
                    ],
                ],
                'en' => [
                    [
                        'type' => 'hero',
                        'data' => ['title' => 'Hero'],
                    ],
                ],
            ],
        ]);

        $response = $this->getJson("/api/tiles/{$tile->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('de', $data['background_blocks']);
        $this->assertArrayHasKey('en', $data['background_blocks']);
        $this->assertCount(1, $data['background_blocks']['de']);
        $this->assertEquals('Held', $data['background_blocks']['de'][0]['props']['title']);
        $this->assertEquals('Hero', $data['background_blocks']['en'][0]['props']['title']);
    }

    public function test_api_returns_tile_with_multiple_background_blocks(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'slug' => ['de' => 'test-tile', 'en' => 'test-tile'],
            'background_blocks' => [
                'de' => [
                    [
                        'type' => 'hero',
                        'data' => ['title' => 'Hero Title'],
                    ],
                    [
                        'type' => 'text-image',
                        'data' => ['text' => 'Some text', 'image' => 'image.jpg'],
                    ],
                ],
                'en' => [],
            ],
        ]);

        $response = $this->getJson("/api/tiles/{$tile->id}?locale=de");

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
                'de' => [
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
                'en' => [],
            ],
        ]);

        $response = $this->getJson("/api/tiles/{$tile->id}?locale=de");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['background_blocks']);
        $this->assertEquals('hero', $data['background_blocks'][0]['type']);
        $this->assertArrayNotHasKey('is_active', $data['background_blocks'][0]['props']);
    }

    public function test_api_returns_empty_blocks_when_tile_has_no_background_blocks(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'slug' => ['de' => 'test-tile', 'en' => 'test-tile'],
        ]);

        $response = $this->getJson("/api/tiles/{$tile->id}?locale=de");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEmpty($data['background_blocks']);
    }

    public function test_api_handles_blocks_without_data_key(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'slug' => ['de' => 'test-tile', 'en' => 'test-tile'],
            'background_blocks' => [
                'de' => [
                    [
                        'type' => 'hero',
                        'title' => 'Hero Title',
                        'subtitle' => 'Hero Subtitle',
                    ],
                ],
                'en' => [],
            ],
        ]);

        $response = $this->getJson("/api/tiles/{$tile->id}?locale=de");

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
                'de' => [
                    ['type' => 'hero', 'data' => ['title' => 'Test']],
                ],
                'en' => [],
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

    public function test_api_returns_jump_mark_label_in_block_props(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'slug' => ['de' => 'test-tile', 'en' => 'test-tile'],
            'background_blocks' => [
                'de' => [
                    [
                        'type' => 'intro-text',
                        'data' => [
                            'heading' => 'Intro',
                            'text' => 'Some text',
                            'jump_mark_label' => 'Hintergrund',
                        ],
                    ],
                ],
                'en' => [
                    [
                        'type' => 'intro-text',
                        'data' => [
                            'heading' => 'Intro',
                            'text' => 'Some text',
                            'jump_mark_label' => 'Background',
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->getJson("/api/tiles/{$tile->id}?locale=de");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('Hintergrund', $data['background_blocks'][0]['props']['jump_mark_label']);

        $response = $this->getJson("/api/tiles/{$tile->id}?locale=en");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('Background', $data['background_blocks'][0]['props']['jump_mark_label']);
    }

    public function test_api_returns_locale_specific_background_blocks(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'slug' => ['de' => 'test-tile', 'en' => 'test-tile'],
            'background_blocks' => [
                'de' => [
                    [
                        'type' => 'intro-text',
                        'data' => [
                            'heading' => 'Deutsche Ueberschrift',
                            'text' => 'Deutscher Text',
                        ],
                    ],
                ],
                'en' => [
                    [
                        'type' => 'intro-text',
                        'data' => [
                            'heading' => 'English Heading',
                            'text' => 'English Text',
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->getJson("/api/tiles/{$tile->id}?locale=de");
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('Deutsche Ueberschrift', $data['background_blocks'][0]['props']['heading']);

        $response = $this->getJson("/api/tiles/{$tile->id}?locale=en");
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('English Heading', $data['background_blocks'][0]['props']['heading']);
    }
}
