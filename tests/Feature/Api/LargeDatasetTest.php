<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LargeDatasetTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::where('slug', 'default')->first();
    }

    public function test_tiles_api_returns_all_public_tiles_from_large_dataset(): void
    {
        // Create 25 public tiles
        $publicTiles = Tile::factory()
            ->forTenant($this->tenant)
            ->count(25)
            ->create(['is_public' => true]);

        // Create 5 private tiles that should not appear
        Tile::factory()
            ->forTenant($this->tenant)
            ->count(5)
            ->create(['is_public' => false]);

        $response = $this->getJson('/api/tiles?locale=de');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(25, $data, 'Should return exactly 25 public tiles');
    }

    public function test_tiles_api_returns_tiles_ordered_by_position(): void
    {
        // Create tiles with explicit descending positions to verify sorting
        $positions = [30, 10, 20, 5, 25, 15, 1, 35, 40, 50];
        foreach ($positions as $position) {
            Tile::factory()->forTenant($this->tenant)->create([
                'position' => $position,
                'is_public' => true,
            ]);
        }

        $response = $this->getJson('/api/tiles?locale=de');
        $response->assertOk();

        $data = $response->json('data');
        $returnedPositions = array_column($data, 'position');

        // If position is not in the resource, check ordering by comparing to sorted
        // The TileResource doesn't expose position, so we check IDs are in order
        // Actually let's verify count first
        $this->assertCount(10, $data);

        // The API orders by position ASC. Verify the IDs match the position order.
        $orderedTiles = Tile::where('tenant_id', $this->tenant->id)
            ->where('is_public', true)
            ->orderBy('position')
            ->pluck('id')
            ->toArray();

        $returnedIds = array_column($data, 'id');
        $this->assertEquals($orderedTiles, $returnedIds, 'Tiles should be ordered by position ascending');
    }

    public function test_tiles_api_excludes_other_tenant_tiles(): void
    {
        // Create tiles for the default tenant
        Tile::factory()
            ->forTenant($this->tenant)
            ->count(3)
            ->create(['is_public' => true]);

        // Create tiles for a different tenant
        $otherTenant = Tenant::factory()->create(['slug' => 'other-city']);
        Tile::factory()
            ->forTenant($otherTenant)
            ->count(5)
            ->create(['is_public' => true]);

        $response = $this->getJson('/api/tiles?locale=de');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(3, $data, 'Should only return tiles belonging to the default tenant');
    }
}
