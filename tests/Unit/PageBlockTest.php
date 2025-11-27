<?php

namespace Tests\Unit;

use App\Filament\Fabricator\PageBlocks\HeroBlock;
use App\Filament\Fabricator\PageBlocks\IntroTextBlock;
use App\Filament\Fabricator\PageBlocks\TextImageBlock;
use App\Filament\Fabricator\PageBlocks\SectionBlock;
use App\Filament\Fabricator\PageBlocks\ListBlock;
use App\Filament\Fabricator\PageBlocks\FAQBlock;
use App\Filament\Fabricator\PageBlocks\LinkBlock;
use App\Filament\Fabricator\PageBlocks\TileAppBlock;
use Tests\TestCase;

class PageBlockTest extends TestCase
{
    public function test_hero_block_has_correct_handle(): void
    {
        $schema = HeroBlock::getBlockSchema();
        $this->assertEquals('hero', $schema->getName());
    }

    public function test_hero_block_schema_has_required_fields(): void
    {
        $schema = HeroBlock::getBlockSchema();
        $fields = $schema->getChildComponents();
        
        $fieldNames = array_map(fn($field) => $field->getName(), $fields);
        
        $this->assertContains('title', $fieldNames);
        $this->assertContains('subtitle', $fieldNames);
        $this->assertContains('image', $fieldNames);
    }

    public function test_intro_text_block_has_correct_handle(): void
    {
        $schema = IntroTextBlock::getBlockSchema();
        $this->assertEquals('intro-text', $schema->getName());
    }

    public function test_intro_text_block_schema_has_required_fields(): void
    {
        $schema = IntroTextBlock::getBlockSchema();
        $fields = $schema->getChildComponents();
        
        $fieldNames = array_map(fn($field) => $field->getName(), $fields);
        
        $this->assertContains('heading', $fieldNames);
        $this->assertContains('text', $fieldNames);
    }

    public function test_text_image_block_has_correct_handle(): void
    {
        $schema = TextImageBlock::getBlockSchema();
        $this->assertEquals('text-image', $schema->getName());
    }

    public function test_text_image_block_schema_has_required_fields(): void
    {
        $schema = TextImageBlock::getBlockSchema();
        $fields = $schema->getChildComponents();
        
        $fieldNames = array_map(fn($field) => $field->getName(), $fields);
        
        $this->assertContains('text', $fieldNames);
        $this->assertContains('image', $fieldNames);
        $this->assertContains('image_position', $fieldNames);
    }

    public function test_blocks_have_correct_component_names(): void
    {
        $this->assertEquals('filament-fabricator.page-blocks.hero', HeroBlock::getComponent());
        $this->assertEquals('filament-fabricator.page-blocks.intro-text', IntroTextBlock::getComponent());
        $this->assertEquals('filament-fabricator.page-blocks.text-image', TextImageBlock::getComponent());
    }

    public function test_section_block_has_correct_handle(): void
    {
        $schema = SectionBlock::getBlockSchema();
        $this->assertEquals('section', $schema->getName());
    }

    public function test_section_block_schema_has_optional_fields(): void
    {
        $schema = SectionBlock::getBlockSchema();
        $fields = $schema->getChildComponents();
        
        $fieldNames = array_map(fn($field) => $field->getName(), $fields);
        
        $this->assertContains('title', $fieldNames);
        $this->assertContains('background_color', $fieldNames);
    }

    public function test_list_block_has_correct_handle(): void
    {
        $schema = ListBlock::getBlockSchema();
        $this->assertEquals('list', $schema->getName());
    }

    public function test_list_block_schema_has_required_fields(): void
    {
        $schema = ListBlock::getBlockSchema();
        $fields = $schema->getChildComponents();
        
        $fieldNames = array_map(fn($field) => $field->getName(), $fields);
        
        $this->assertContains('items', $fieldNames);
        $this->assertContains('list_type', $fieldNames);
    }

    public function test_faq_block_has_correct_handle(): void
    {
        $schema = FAQBlock::getBlockSchema();
        $this->assertEquals('faq', $schema->getName());
    }

    public function test_faq_block_schema_has_required_fields(): void
    {
        $schema = FAQBlock::getBlockSchema();
        $fields = $schema->getChildComponents();
        
        $fieldNames = array_map(fn($field) => $field->getName(), $fields);
        
        $this->assertContains('items', $fieldNames);
    }

    public function test_link_block_has_correct_handle(): void
    {
        $schema = LinkBlock::getBlockSchema();
        $this->assertEquals('link', $schema->getName());
    }

    public function test_link_block_schema_has_required_fields(): void
    {
        $schema = LinkBlock::getBlockSchema();
        $fields = $schema->getChildComponents();
        
        $fieldNames = array_map(fn($field) => $field->getName(), $fields);
        
        $this->assertContains('text', $fieldNames);
        $this->assertContains('url', $fieldNames);
        $this->assertContains('target', $fieldNames);
        $this->assertContains('style', $fieldNames);
    }

    public function test_tile_app_block_has_correct_handle(): void
    {
        $schema = TileAppBlock::getBlockSchema();
        $this->assertEquals('tile-app', $schema->getName());
    }

    public function test_tile_app_block_schema_has_expected_fields(): void
    {
        $schema = TileAppBlock::getBlockSchema();
        $fields = $schema->getChildComponents();

        $fieldNames = array_map(fn($field) => $field->getName(), $fields);

        $this->assertContains('mode', $fieldNames);
        $this->assertContains('initial_category', $fieldNames);
        $this->assertContains('use_mock_data', $fieldNames);
    }
}


