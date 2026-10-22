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
        $fieldNames = $this->fieldNames($schema->getChildComponents());

        $this->assertContains('heading', $fieldNames);
        $this->assertContains('text', $fieldNames);
        foreach (['image', 'image_secondary'] as $image) {
            foreach (['', '_title', '_alt', '_copyright'] as $suffix) {
                $this->assertContains($image.$suffix, $fieldNames);
            }
        }
    }

    public function test_text_image_block_has_correct_handle(): void
    {
        $schema = TextImageBlock::getBlockSchema();
        $this->assertEquals('text-image', $schema->getName());
    }

    public function test_text_image_block_schema_has_required_fields(): void
    {
        $schema = TextImageBlock::getBlockSchema();
        $fieldNames = $this->fieldNames($schema->getChildComponents());

        $this->assertContains('text', $fieldNames);
        foreach (['image', 'image_title', 'image_alt', 'image_copyright', 'image_position'] as $field) {
            $this->assertContains($field, $fieldNames);
        }
    }

    public function test_slider_block_has_correct_handle(): void
    {
        $schema = SliderBlock::getBlockSchema();
        $this->assertEquals('slider', $schema->getName());
    }

    public function test_slider_block_schema_has_required_fields(): void
    {
        $schema = SliderBlock::getBlockSchema();
        $fieldNames = $this->fieldNames($schema->getChildComponents());

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
        $fieldNames = $this->fieldNames($schema->getChildComponents());

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
        $fieldNames = $this->fieldNames($schema->getChildComponents());

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

    /**
     * Field names of a schema, including fields nested in layout components such as Fieldset.
     */
    private function fieldNames(array $components): array
    {
        return collect($components)
            ->flatMap(fn ($component) => [
                ...(method_exists($component, 'getName') ? [$component->getName()] : []),
                ...$this->fieldNames($component->getChildComponents()),
            ])
            ->all();
    }
}
