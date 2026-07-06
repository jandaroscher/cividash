<?php

namespace Tests\Unit;

use App\Services\Content\BlockTransformer;
use Tests\TestCase;

class BlockTransformerTest extends TestCase
{
    private BlockTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new BlockTransformer;
    }

    public function test_transforms_blocks_with_data_key(): void
    {
        $blocks = [
            [
                'type' => 'hero',
                'data' => ['heading' => 'Test', 'subtitle' => 'Sub'],
            ],
        ];

        $result = $this->transformer->transform($blocks);

        $this->assertCount(1, $result);
        $this->assertEquals('hero', $result[0]['type']);
        $this->assertEquals(['heading' => 'Test', 'subtitle' => 'Sub'], $result[0]['props']);
    }

    public function test_transforms_blocks_with_flat_properties(): void
    {
        $blocks = [
            [
                'type' => 'hero',
                'heading' => 'Test',
                'subtitle' => 'Sub',
            ],
        ];

        $result = $this->transformer->transform($blocks);

        $this->assertCount(1, $result);
        $this->assertEquals('hero', $result[0]['type']);
        $this->assertEquals(['heading' => 'Test', 'subtitle' => 'Sub'], $result[0]['props']);
    }

    public function test_omits_blocks_without_type(): void
    {
        $blocks = [
            ['heading' => 'No type or handle'],
            ['type' => 'hero', 'data' => ['heading' => 'Valid']],
        ];

        $result = $this->transformer->transform($blocks);

        $this->assertCount(1, $result);
        $this->assertEquals('hero', $result[0]['type']);
    }

    public function test_filters_inactive_blocks_in_data(): void
    {
        $blocks = [
            [
                'type' => 'hero',
                'data' => ['heading' => 'Active', 'is_active' => true],
            ],
            [
                'type' => 'text',
                'data' => ['content' => 'Inactive', 'is_active' => false],
            ],
        ];

        $result = $this->transformer->transform($blocks);

        $this->assertCount(1, $result);
        $this->assertEquals('hero', $result[0]['type']);
    }

    public function test_filters_inactive_blocks_at_top_level(): void
    {
        $blocks = [
            [
                'type' => 'hero',
                'data' => ['heading' => 'Test'],
                'is_active' => false,
            ],
        ];

        $result = $this->transformer->transform($blocks);

        $this->assertCount(0, $result);
    }

    public function test_handles_empty_blocks_array(): void
    {
        $result = $this->transformer->transform([]);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function test_supports_handle_key_as_type_alias(): void
    {
        $blocks = [
            [
                'handle' => 'section',
                'data' => ['content' => 'Test'],
            ],
        ];

        $result = $this->transformer->transform($blocks);

        $this->assertCount(1, $result);
        $this->assertEquals('section', $result[0]['type']);
        $this->assertEquals(['content' => 'Test'], $result[0]['props']);
    }

    public function test_is_active_in_data_gets_removed_from_props(): void
    {
        $blocks = [
            [
                'type' => 'hero',
                'data' => ['heading' => 'Test', 'is_active' => true],
            ],
        ];

        $result = $this->transformer->transform($blocks);

        $this->assertCount(1, $result);
        $this->assertArrayNotHasKey('is_active', $result[0]['props']);
        $this->assertEquals(['heading' => 'Test'], $result[0]['props']);
    }

    public function test_is_active_at_top_level_does_not_appear_in_props_when_data_key_used(): void
    {
        $blocks = [
            [
                'type' => 'hero',
                'data' => ['heading' => 'Test'],
                'is_active' => true,
            ],
        ];

        $result = $this->transformer->transform($blocks);

        $this->assertCount(1, $result);
        // Props come from data key, so top-level is_active should not be in props
        $this->assertEquals(['heading' => 'Test'], $result[0]['props']);
    }

    public function test_type_key_takes_priority_over_handle(): void
    {
        $blocks = [
            [
                'type' => 'hero',
                'handle' => 'section',
                'data' => ['heading' => 'Test'],
            ],
        ];

        $result = $this->transformer->transform($blocks);

        $this->assertCount(1, $result);
        $this->assertEquals('hero', $result[0]['type']);
    }

    public function test_flat_properties_exclude_metadata_keys(): void
    {
        $blocks = [
            [
                'type' => 'hero',
                'handle' => 'hero-handle',
                'id' => 123,
                'uuid' => 'abc-123',
                'heading' => 'Test',
            ],
        ];

        $result = $this->transformer->transform($blocks);

        $this->assertCount(1, $result);
        $this->assertArrayNotHasKey('type', $result[0]['props']);
        $this->assertArrayNotHasKey('handle', $result[0]['props']);
        $this->assertArrayNotHasKey('id', $result[0]['props']);
        $this->assertArrayNotHasKey('uuid', $result[0]['props']);
        $this->assertEquals(['heading' => 'Test'], $result[0]['props']);
    }

    public function test_multiple_blocks_preserve_order(): void
    {
        $blocks = [
            ['type' => 'hero', 'data' => ['heading' => 'First']],
            ['type' => 'text', 'data' => ['content' => 'Second']],
            ['type' => 'faq', 'data' => ['items' => []]],
        ];

        $result = $this->transformer->transform($blocks);

        $this->assertCount(3, $result);
        $this->assertEquals('hero', $result[0]['type']);
        $this->assertEquals('text', $result[1]['type']);
        $this->assertEquals('faq', $result[2]['type']);
    }

    public function test_trims_whitespace_from_type(): void
    {
        // Legacy/imported data can contain stray whitespace around the type,
        // which would otherwise fail to match a block's registered name on the
        // frontend and silently hide the block.
        $blocks = [
            [
                'type' => "faq\n",
                'data' => ['items' => []],
            ],
        ];

        $result = $this->transformer->transform($blocks);

        $this->assertCount(1, $result);
        $this->assertEquals('faq', $result[0]['type']);
    }

    public function test_trims_whitespace_from_handle_when_used_as_type_alias(): void
    {
        $blocks = [
            [
                'handle' => '  faq  ',
                'data' => ['items' => []],
            ],
        ];

        $result = $this->transformer->transform($blocks);

        $this->assertCount(1, $result);
        $this->assertEquals('faq', $result[0]['type']);
    }

    public function test_omits_block_whose_type_is_only_whitespace(): void
    {
        $blocks = [
            ['type' => '   ', 'data' => ['items' => []]],
            ['type' => 'faq', 'data' => ['items' => []]],
        ];

        $result = $this->transformer->transform($blocks);

        $this->assertCount(1, $result);
        $this->assertEquals('faq', $result[0]['type']);
    }

    public function test_reindexes_after_filtering(): void
    {
        $blocks = [
            ['type' => 'hero', 'data' => ['heading' => 'First']],
            ['data' => ['content' => 'No type - filtered']],
            ['type' => 'text', 'data' => ['content' => 'Third']],
        ];

        $result = $this->transformer->transform($blocks);

        $this->assertCount(2, $result);
        // After filtering, keys should be re-indexed (0, 1)
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertEquals('hero', $result[0]['type']);
        $this->assertEquals('text', $result[1]['type']);
    }
}
