<?php

namespace Tests\Unit;

use Tests\TestCase;

class PageBlockRegistrationTest extends TestCase
{
    /**
     * Test that all PageBlocks are registered in filament-fabricator config.
     */
    public function test_all_page_blocks_are_registered_in_config(): void
    {
        $config = config('filament-fabricator');
        
        $registeredBlocks = $config['page-blocks']['register'] ?? [];
        
        // Assert that all blocks are registered
        $expectedBlocks = [
            \App\Filament\Fabricator\PageBlocks\HeroBlock::class,
            \App\Filament\Fabricator\PageBlocks\IntroTextBlock::class,
            \App\Filament\Fabricator\PageBlocks\TextImageBlock::class,
            \App\Filament\Fabricator\PageBlocks\SliderBlock::class,
            \App\Filament\Fabricator\PageBlocks\SectionBlock::class,
            \App\Filament\Fabricator\PageBlocks\ListBlock::class,
            \App\Filament\Fabricator\PageBlocks\FAQBlock::class,
            \App\Filament\Fabricator\PageBlocks\LinkBlock::class,
            \App\Filament\Fabricator\PageBlocks\TileAppBlock::class,
        ];
        
        foreach ($expectedBlocks as $blockClass) {
            $this->assertContains(
                $blockClass,
                $registeredBlocks,
                "Block {$blockClass} should be registered in config"
            );
        }
    }

    /**
     * Test that all registered blocks can be resolved by Fabricator.
     */
    public function test_all_blocks_can_be_resolved_by_fabricator(): void
    {
        $config = config('filament-fabricator');
        $registeredBlocks = $config['page-blocks']['register'] ?? [];
        
        foreach ($registeredBlocks as $blockClass) {
            // Test that the block class exists and has required methods
            $this->assertTrue(
                class_exists($blockClass),
                "Block class {$blockClass} should exist"
            );
            
            // Test that getBlockSchema method exists and returns a Block
            $schema = $blockClass::getBlockSchema();
            $this->assertNotNull($schema, "Block {$blockClass} should return a schema");
            $this->assertNotEmpty($blockClass::getName(), "Block {$blockClass} should have a name");
        }
    }
}


