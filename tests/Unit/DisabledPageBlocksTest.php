<?php

namespace Tests\Unit;

use App\Filament\Fabricator\DisabledPageBlocks\HeroBlock;
use App\Filament\Fabricator\DisabledPageBlocks\LinkBlock;
use App\Filament\Fabricator\DisabledPageBlocks\ListBlock;
use App\Filament\Fabricator\DisabledPageBlocks\SectionBlock;
use App\Filament\Fabricator\DisabledPageBlocks\TileAppBlock;
use Filament\Forms\Components\Builder\Block;
use Tests\TestCase;

class DisabledPageBlocksTest extends TestCase
{
    // ── HeroBlock ──

    public function test_hero_block_returns_block_schema(): void
    {
        $schema = HeroBlock::getBlockSchema();

        $this->assertInstanceOf(Block::class, $schema);
        $this->assertEquals('hero', $schema->getName());
    }

    public function test_hero_block_mutate_data_passes_through(): void
    {
        $data = ['title' => 'Test', 'subtitle' => 'Sub', 'image' => 'hero.jpg'];

        $result = HeroBlock::mutateData($data);

        $this->assertEquals($data, $result);
    }

    // ── LinkBlock ──

    public function test_link_block_returns_block_schema(): void
    {
        $schema = LinkBlock::getBlockSchema();

        $this->assertInstanceOf(Block::class, $schema);
        $this->assertEquals('link', $schema->getName());
    }

    public function test_link_block_mutate_data_passes_through(): void
    {
        $data = ['text' => 'Click me', 'url' => 'https://example.com', 'target' => '_blank', 'style' => 'primary'];

        $result = LinkBlock::mutateData($data);

        $this->assertEquals($data, $result);
    }

    // ── ListBlock ──

    public function test_list_block_returns_block_schema(): void
    {
        $schema = ListBlock::getBlockSchema();

        $this->assertInstanceOf(Block::class, $schema);
        $this->assertEquals('list', $schema->getName());
    }

    public function test_list_block_mutate_data_passes_through(): void
    {
        $data = ['items' => [['text' => 'Item 1']], 'list_type' => 'bullet'];

        $result = ListBlock::mutateData($data);

        $this->assertEquals($data, $result);
    }

    // ── SectionBlock ──

    public function test_section_block_returns_block_schema(): void
    {
        $schema = SectionBlock::getBlockSchema();

        $this->assertInstanceOf(Block::class, $schema);
        $this->assertEquals('section', $schema->getName());
    }

    public function test_section_block_mutate_data_passes_through(): void
    {
        $data = ['title' => 'Section Title', 'background_color' => '#ff0000'];

        $result = SectionBlock::mutateData($data);

        $this->assertEquals($data, $result);
    }

    // ── TileAppBlock ──

    public function test_tile_app_block_returns_block_schema(): void
    {
        $schema = TileAppBlock::getBlockSchema();

        $this->assertInstanceOf(Block::class, $schema);
        $this->assertEquals('tile-app', $schema->getName());
    }

    public function test_tile_app_block_mutate_data_casts_booleans(): void
    {
        $data = ['show_search' => 1, 'show_filter' => 0];

        $result = TileAppBlock::mutateData($data);

        $this->assertTrue($result['show_search']);
        $this->assertFalse($result['show_filter']);
    }

    public function test_tile_app_block_mutate_data_defaults_missing_keys_to_true(): void
    {
        $data = ['other_key' => 'value'];

        $result = TileAppBlock::mutateData($data);

        $this->assertTrue($result['show_search']);
        $this->assertTrue($result['show_filter']);
        $this->assertEquals('value', $result['other_key']);
    }

    public function test_tile_app_block_mutate_data_preserves_false_values(): void
    {
        $data = ['show_search' => false, 'show_filter' => false];

        $result = TileAppBlock::mutateData($data);

        $this->assertFalse($result['show_search']);
        $this->assertFalse($result['show_filter']);
    }
}
