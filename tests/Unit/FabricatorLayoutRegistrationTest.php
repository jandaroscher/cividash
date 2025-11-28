<?php

namespace Tests\Unit;

use Tests\TestCase;

class FabricatorLayoutRegistrationTest extends TestCase
{
    /**
     * Test that layouts are registered in filament-fabricator config.
     */
    public function test_layouts_are_registered_in_config(): void
    {
        $config = config('filament-fabricator');
        
        $registeredLayouts = $config['layouts']['register'] ?? [];
        
        // Assert that both layouts are registered
        $this->assertContains(
            \App\Filament\Fabricator\Layouts\LandingpageLayout::class,
            $registeredLayouts,
            'LandingpageLayout should be registered in config'
        );
        
        $this->assertContains(
            \App\Filament\Fabricator\Layouts\SubpageLayout::class,
            $registeredLayouts,
            'SubpageLayout should be registered in config'
        );
    }
}





