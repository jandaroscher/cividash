<?php

namespace Tests\Unit;

use Tests\TestCase;

class FilamentFabricatorConfigTest extends TestCase
{
    /**
     * Test that filament-fabricator config has required settings.
     */
    public function test_fabricator_config_has_required_settings(): void
    {
        $config = config('filament-fabricator');

        // Assert routing is enabled
        $this->assertTrue(
            $config['routing']['enabled'] ?? false,
            'routing.enabled should be true'
        );

        // Assert layouts array exists
        $this->assertIsArray(
            $config['layouts'] ?? null,
            'layouts array should exist'
        );

        // Assert page-blocks array exists (note: in v2.7 it's 'page-blocks', not 'blocks')
        $this->assertIsArray(
            $config['page-blocks'] ?? null,
            'page-blocks array should exist'
        );

        // Assert routing prefix is set (should be null or empty string)
        $this->assertArrayHasKey(
            'prefix',
            $config['routing'],
            'routing.prefix should be defined'
        );
    }
}
