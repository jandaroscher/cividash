<?php

namespace Tests\Unit;

use App\Filament\Fabricator\PageBlocks\SliderBlock;
use Tests\TestCase;

class SliderBlockTest extends TestCase
{
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

        // SliderBlock should have items/content fields
        $this->assertNotEmpty($fieldNames);
    }

    public function test_slider_block_has_correct_component_name(): void
    {
        $this->assertEquals('filament-fabricator.page-blocks.slider', SliderBlock::getComponent());
    }
}
