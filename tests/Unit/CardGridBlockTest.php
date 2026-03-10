<?php

namespace Tests\Unit;

use App\Filament\Fabricator\PageBlocks\CardGridBlock;
use Tests\TestCase;

class CardGridBlockTest extends TestCase
{

    public function test_mutate_data_returns_empty_array_when_no_tiles_selected(): void
    {
        // Test mutateData with empty tiles array — frontend shows all tiles when array is empty
        $data = ['tiles' => []];
        $mutated = CardGridBlock::mutateData($data);

        $this->assertArrayHasKey('tiles', $mutated);
        $this->assertIsArray($mutated['tiles']);
        $this->assertCount(0, $mutated['tiles']);
    }

    public function test_mutate_data_returns_empty_array_when_tiles_not_set(): void
    {
        $mutated = CardGridBlock::mutateData([]);

        $this->assertArrayHasKey('tiles', $mutated);
        $this->assertIsArray($mutated['tiles']);
        $this->assertCount(0, $mutated['tiles']);
    }

    public function test_mutate_data_preserves_selected_tile_ids(): void
    {
        $data = ['tiles' => [1, 5, 3]];
        $mutated = CardGridBlock::mutateData($data);

        $this->assertArrayHasKey('tiles', $mutated);
        $this->assertIsArray($mutated['tiles']);
        $this->assertEquals([1, 5, 3], $mutated['tiles']);
    }
}
