<?php

namespace Tests\Unit;

use App\Filament\Fabricator\PageBlocks\CardGridBlock;
use App\Models\Tenant;
use App\Models\Tile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardGridBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    public function test_mutate_data_loads_all_tiles_when_empty(): void
    {
        // Set up tenant context for the test
        $user = \App\Models\User::factory()->create();
        $tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $user->tenants()->sync([$tenant->id]);
        Filament::auth()->login($user);
        Filament::setTenant($tenant);

        // Create test tiles (will be automatically assigned to the current tenant)
        $tile1 = Tile::create([
            'title' => ['de' => 'Tile 1', 'en' => 'Tile 1'],
            'description' => ['de' => 'Description 1', 'en' => 'Description 1'],
            'position' => 1,
        ]);

        $tile2 = Tile::create([
            'title' => ['de' => 'Tile 2', 'en' => 'Tile 2'],
            'description' => ['de' => 'Description 2', 'en' => 'Description 2'],
            'position' => 2,
        ]);

        app()->setLocale('de');

        // Test mutateData with empty tiles array
        $data = ['tiles' => []];
        $mutated = CardGridBlock::mutateData($data);

        // Should have loaded all tile IDs (translations happen at render time, not mutation time)
        $this->assertArrayHasKey('tiles', $mutated);
        $this->assertIsArray($mutated['tiles']);
        $this->assertCount(2, $mutated['tiles']);
        $this->assertContains($tile1->id, $mutated['tiles']);
        $this->assertContains($tile2->id, $mutated['tiles']);
        // Should NOT have tile_models - translations are done at render time
        $this->assertArrayNotHasKey('tile_models', $mutated);
    }

    public function test_mutate_data_loads_selected_tiles(): void
    {
        // Set up tenant context for the test
        $user = \App\Models\User::factory()->create();
        $tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $user->tenants()->sync([$tenant->id]);
        Filament::auth()->login($user);
        Filament::setTenant($tenant);

        // Create test tiles (will be automatically assigned to the current tenant)
        $tile1 = Tile::create([
            'title' => ['de' => 'Tile 1', 'en' => 'Tile 1'],
            'position' => 1,
        ]);

        $tile2 = Tile::create([
            'title' => ['de' => 'Tile 2', 'en' => 'Tile 2'],
            'position' => 2,
        ]);

        app()->setLocale('de');

        // Test mutateData with selected tiles
        $data = ['tiles' => [$tile1->id]];
        $mutated = CardGridBlock::mutateData($data);

        // Should have only selected tile ID (translations happen at render time, not mutation time)
        $this->assertArrayHasKey('tiles', $mutated);
        $this->assertIsArray($mutated['tiles']);
        $this->assertCount(1, $mutated['tiles']);
        $this->assertEquals($tile1->id, $mutated['tiles'][0]);
        // Should NOT have tile_models - translations are done at render time
        $this->assertArrayNotHasKey('tile_models', $mutated);
    }
}



