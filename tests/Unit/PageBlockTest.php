<?php

namespace Tests\Unit;

use App\Filament\Fabricator\PageBlocks\CardGridBlock;
use App\Filament\Fabricator\PageBlocks\FAQBlock;
use App\Filament\Fabricator\PageBlocks\IntroTextBlock;
use App\Filament\Fabricator\PageBlocks\SliderBlock;
use App\Filament\Fabricator\PageBlocks\TextImageBlock;
use Tests\TestCase;

class PageBlockTest extends TestCase
{
    public function test_intro_text_block_has_correct_handle(): void
    {
        $schema = IntroTextBlock::getBlockSchema();
        $this->assertEquals('intro-text', $schema->getName());
    }

    public function test_intro_text_block_schema_has_required_fields(): void
    {
        $schema = IntroTextBlock::getBlockSchema();
        $fields = $schema->getChildComponents();

        $fieldNames = array_map(fn ($field) => $field->getName(), $fields);

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

        $fieldNames = array_map(fn ($field) => $field->getName(), $fields);

        $this->assertContains('text', $fieldNames);
        $this->assertContains('image', $fieldNames);
        $this->assertContains('image_position', $fieldNames);
    }

    public function test_slider_block_has_correct_handle(): void
    {
        $schema = SliderBlock::getBlockSchema();
        $this->assertEquals('slider', $schema->getName());
    }

    public function test_slider_block_schema_has_required_fields(): void
    {
        $schema = SliderBlock::getBlockSchema();
        $fields = $schema->getChildComponents();

        $fieldNames = array_map(fn ($field) => $field->getName(), $fields);

        $this->assertContains('items', $fieldNames);
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

        $fieldNames = array_map(fn ($field) => $field->getName(), $fields);

        $this->assertContains('items', $fieldNames);
    }

    public function test_card_grid_block_has_correct_handle(): void
    {
        $schema = CardGridBlock::getBlockSchema();
        $this->assertEquals('card-grid', $schema->getName());
    }

    public function test_card_grid_block_schema_has_expected_fields(): void
    {
        $schema = CardGridBlock::getBlockSchema();
        $fields = $schema->getChildComponents();

        $fieldNames = array_map(fn ($field) => $field->getName(), $fields);

        $this->assertContains('tiles', $fieldNames);
    }

    public function test_blocks_have_correct_component_names(): void
    {
        $this->assertEquals('filament-fabricator.page-blocks.intro-text', IntroTextBlock::getComponent());
        $this->assertEquals('filament-fabricator.page-blocks.text-image', TextImageBlock::getComponent());
        $this->assertEquals('filament-fabricator.page-blocks.slider', SliderBlock::getComponent());
        $this->assertEquals('filament-fabricator.page-blocks.faq', FAQBlock::getComponent());
        $this->assertEquals('filament-fabricator.page-blocks.card-grid', CardGridBlock::getComponent());
    }
}
