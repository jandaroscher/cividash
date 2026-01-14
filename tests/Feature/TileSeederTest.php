<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Tenant;
use App\Models\Tile;
use App\Services\ParsedTile;
use Database\Seeders\TileSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TileSeederTest extends TestCase
{
    use RefreshDatabase;

    protected ?TileSeeder $seeder = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create user and authenticate for Filament tenant context
        $user = \App\Models\User::factory()->create();
        $tenant = Tenant::where('slug', 'default')->first();
        if ($tenant) {
            $user->tenants()->sync([$tenant->id]);
            Filament::auth()->login($user);
            Filament::setTenant($tenant);
        }
        
        $this->seeder = new TileSeeder();
    }

    public function test_tile_seeder_creates_tiles(): void
    {
        $tiles = collect([
            new ParsedTile(
                id: 1,
                title: 'Test Tile',
                titleEn: 'Test Tile EN',
                description: 'Description',
                descriptionEn: 'Description EN',
                position: 1,
                icon: null,
                backgroundText: null,
                backgroundTextEn: null,
                contributionText: null,
                contributionTextEn: null,
                sliderData: [],
                categoryIds: [],
                metricIds: [],
                handlungsdimension: null,
                sdgZielIds: [],
            ),
        ]);

        $categoryIdMap = [];
        $handlungsdimensionIdMap = [];
        $sdgZielIdMap = [];

        $this->seeder->run($tiles, $categoryIdMap, $handlungsdimensionIdMap, $sdgZielIdMap);

        $this->assertDatabaseCount('tiles', 1);
        $tile = Tile::whereJsonContains('title->de', 'Test Tile')->first();
        $this->assertNotNull($tile);
        $this->assertEquals('Test Tile', $tile->getTranslation('title', 'de'));
        $this->assertEquals('Test Tile EN', $tile->getTranslation('title', 'en'));
    }

    public function test_tile_seeder_is_idempotent(): void
    {
        $tiles = collect([
            new ParsedTile(
                id: 1,
                title: 'Test Tile',
                titleEn: null,
                description: 'Description',
                descriptionEn: null,
                position: 1,
                icon: null,
                backgroundText: null,
                backgroundTextEn: null,
                contributionText: null,
                contributionTextEn: null,
                sliderData: [],
                categoryIds: [],
                metricIds: [],
                handlungsdimension: null,
                sdgZielIds: [],
            ),
        ]);

        $categoryIdMap = [];

        // First run
        $this->seeder->run($tiles, $categoryIdMap);
        $count1 = Tile::count();

        // Second run
        $this->seeder->run($tiles, $categoryIdMap);
        $count2 = Tile::count();

        $this->assertEquals($count1, $count2);
    }

    public function test_tile_seeder_handles_translatable_fields(): void
    {
        $tiles = collect([
            new ParsedTile(
                id: 1,
                title: 'Deutscher Titel',
                titleEn: 'English Title',
                description: 'Deutsche Beschreibung',
                descriptionEn: 'English Description',
                position: 1,
                icon: null,
                backgroundText: null,
                backgroundTextEn: null,
                contributionText: null,
                contributionTextEn: null,
                sliderData: [],
                categoryIds: [],
                metricIds: [],
                handlungsdimension: null,
                sdgZielIds: [],
            ),
        ]);

        $categoryIdMap = [];
        $handlungsdimensionIdMap = [];
        $sdgZielIdMap = [];

        $this->seeder->run($tiles, $categoryIdMap, $handlungsdimensionIdMap, $sdgZielIdMap);

        $tile = Tile::whereJsonContains('title->de', 'Deutscher Titel')->first();
        $this->assertNotNull($tile);
        
        // Title should be translatable
        $this->assertEquals('Deutscher Titel', $tile->getTranslation('title', 'de'));
        $this->assertEquals('English Title', $tile->getTranslation('title', 'en'));
        $this->assertEquals('Deutsche Beschreibung', $tile->getTranslation('description', 'de'));
        $this->assertEquals('English Description', $tile->getTranslation('description', 'en'));
    }

    public function test_tile_seeder_preserves_existing_en_translations(): void
    {
        // Create tile with EN translation
        $existing = Tile::create([
            'title' => ['de' => 'Test', 'en' => 'Existing EN'],
            'description' => ['de' => 'DE Desc', 'en' => 'Existing EN Desc'],
            'position' => 1,
        ]);

        $tiles = collect([
            new ParsedTile(
                id: 1,
                title: 'Test',
                titleEn: null, // null should not overwrite existing
                description: 'DE Desc',
                descriptionEn: null, // null should not overwrite existing
                position: 1,
                icon: null,
                backgroundText: null,
                backgroundTextEn: null,
                contributionText: null,
                contributionTextEn: null,
                sliderData: [],
                categoryIds: [],
                metricIds: [],
                handlungsdimension: null,
                sdgZielIds: [],
            ),
        ]);

        $categoryIdMap = [];
        $handlungsdimensionIdMap = [];
        $sdgZielIdMap = [];

        $this->seeder->run($tiles, $categoryIdMap, $handlungsdimensionIdMap, $sdgZielIdMap);

        $existing->refresh();

        // EN translations should be preserved
        $this->assertEquals('Existing EN', $existing->getTranslation('title', 'en'));
        $this->assertEquals('Existing EN Desc', $existing->getTranslation('description', 'en'));
    }

    public function test_tile_seeder_attaches_categories(): void
    {
        // Create categories first
        $cat1 = Category::create(['slug' => ['de' => 'Cat 1', 'en' => null], 'position' => 0]);
        $cat2 = Category::create(['slug' => ['de' => 'Cat 2', 'en' => null], 'position' => 1]);

        $tiles = collect([
            new ParsedTile(
                id: 1,
                title: 'Test Tile',
                titleEn: null,
                description: null,
                descriptionEn: null,
                position: 1,
                icon: null,
                backgroundText: null,
                backgroundTextEn: null,
                contributionText: null,
                contributionTextEn: null,
                sliderData: [],
                categoryIds: [1, 2], // Original category IDs from JSON
                metricIds: [],
                handlungsdimension: null,
                sdgZielIds: [],
            ),
        ]);

        $categoryIdMap = [
            1 => $cat1->id, // Map original ID 1 to database ID
            2 => $cat2->id, // Map original ID 2 to database ID
        ];

        $this->seeder->run($tiles, $categoryIdMap);

        $tile = Tile::whereJsonContains('title->de', 'Test Tile')->first();
        $this->assertCount(2, $tile->categories);
        $this->assertTrue($tile->categories->contains($cat1));
        $this->assertTrue($tile->categories->contains($cat2));
    }

    public function test_tile_seeder_transforms_background_blocks(): void
    {
        $tiles = collect([
            new ParsedTile(
                id: 1,
                title: 'Test Tile',
                titleEn: null,
                description: null,
                descriptionEn: null,
                position: 1,
                icon: null,
                backgroundText: 'Background text content',
                backgroundTextEn: null,
                contributionText: 'Contribution text',
                contributionTextEn: null,
                sliderData: [],
                categoryIds: [],
                metricIds: [],
                handlungsdimension: null,
                sdgZielIds: [],
            ),
        ]);

        $categoryIdMap = [];
        $handlungsdimensionIdMap = [];
        $sdgZielIdMap = [];

        $this->seeder->run($tiles, $categoryIdMap, $handlungsdimensionIdMap, $sdgZielIdMap);

        $tile = Tile::whereJsonContains('title->de', 'Test Tile')->first();
        $this->assertNotNull($tile->background_blocks);
        $this->assertIsArray($tile->background_blocks);
        $this->assertGreaterThan(0, count($tile->background_blocks));

        // Check that blocks have correct structure
        $firstBlock = $tile->background_blocks[0];
        $this->assertArrayHasKey('type', $firstBlock);
        $this->assertArrayHasKey('data', $firstBlock);
    }

    public function test_tile_seeder_validates_block_types(): void
    {
        // This test verifies that block validation against registry works
        // The seeder should fallback to 'intro-text' if block type is invalid
        $tiles = collect([
            new ParsedTile(
                id: 1,
                title: 'Test Tile',
                titleEn: null,
                description: null,
                descriptionEn: null,
                position: 1,
                icon: null,
                backgroundText: 'Background text',
                backgroundTextEn: null,
                contributionText: null,
                contributionTextEn: null,
                sliderData: [],
                categoryIds: [],
                metricIds: [],
                handlungsdimension: null,
                sdgZielIds: [],
            ),
        ]);

        $categoryIdMap = [];
        $handlungsdimensionIdMap = [];
        $sdgZielIdMap = [];

        $this->seeder->run($tiles, $categoryIdMap, $handlungsdimensionIdMap, $sdgZielIdMap);

        $tile = Tile::whereJsonContains('title->de', 'Test Tile')->first();
        $this->assertNotNull($tile->background_blocks);

        // Block type should be valid (intro-text or slider)
        $blockType = $tile->background_blocks[0]['type'] ?? null;
        $this->assertNotNull($blockType);
        $this->assertContains($blockType, ['intro-text', 'slider']);
    }

    public function test_tile_seeder_sets_source_hash_and_last_synced_at(): void
    {
        $tiles = collect([
            new ParsedTile(
                id: 1,
                title: 'Test Tile',
                titleEn: null,
                description: null,
                descriptionEn: null,
                position: 1,
                icon: null,
                backgroundText: null,
                backgroundTextEn: null,
                contributionText: null,
                contributionTextEn: null,
                sliderData: [],
                categoryIds: [],
                metricIds: [],
                handlungsdimension: null,
                sdgZielIds: [],
            ),
        ]);

        $categoryIdMap = [];
        $handlungsdimensionIdMap = [];
        $sdgZielIdMap = [];

        $this->seeder->run($tiles, $categoryIdMap, $handlungsdimensionIdMap, $sdgZielIdMap);

        $tile = Tile::whereJsonContains('title->de', 'Test Tile')->first();
        $this->assertNotNull($tile->source_hash);
        $this->assertNotNull($tile->last_synced_at);
        $this->assertEquals(64, strlen($tile->source_hash)); // SHA256 is 64 chars
    }

    public function test_tile_seeder_handles_slider_data(): void
    {
        $tiles = collect([
            new ParsedTile(
                id: 1,
                title: 'Test Tile',
                titleEn: null,
                description: null,
                descriptionEn: null,
                position: 1,
                icon: null,
                backgroundText: null,
                backgroundTextEn: null,
                contributionText: null,
                contributionTextEn: null,
                sliderData: [
                    [
                        'title' => 'Slider 1',
                        'text' => 'Text 1',
                        'image' => '/path/to/image1.jpg',
                        'link' => 'https://example.com/1',
                    ],
                    [
                        'title' => 'Slider 2',
                        'text' => 'Text 2',
                        'image' => '/path/to/image2.jpg',
                    ],
                ],
                categoryIds: [],
                metricIds: [],
                handlungsdimension: null,
                sdgZielIds: [],
            ),
        ]);

        $categoryIdMap = [];
        $handlungsdimensionIdMap = [];
        $sdgZielIdMap = [];

        $this->seeder->run($tiles, $categoryIdMap, $handlungsdimensionIdMap, $sdgZielIdMap);

        $tile = Tile::whereJsonContains('title->de', 'Test Tile')->first();
        $this->assertNotNull($tile->background_blocks);

        // Find slider block
        $sliderBlock = collect($tile->background_blocks)->firstWhere('type', 'slider');
        $this->assertNotNull($sliderBlock);
        $this->assertArrayHasKey('data', $sliderBlock);
        $this->assertArrayHasKey('items', $sliderBlock['data']);
        $this->assertCount(2, $sliderBlock['data']['items']);
    }
}

