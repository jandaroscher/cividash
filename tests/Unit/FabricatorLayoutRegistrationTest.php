<?php

namespace Tests\Unit;

use App\Filament\Fabricator\Layouts\LandingpageLayout;
use App\Filament\Fabricator\Layouts\SubpageLayout;
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
            LandingpageLayout::class,
            $registeredLayouts,
            'LandingpageLayout should be registered in config'
        );

        $this->assertContains(
            SubpageLayout::class,
            $registeredLayouts,
            'SubpageLayout should be registered in config'
        );
    }
}
