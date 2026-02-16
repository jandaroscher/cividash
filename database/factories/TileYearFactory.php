<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TileYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TileYear>
 */
class TileYearFactory extends Factory
{
    protected $model = TileYear::class;

    public function definition(): array
    {
        return [
            'tile_id' => Tile::factory(),
            'year' => fake()->numberBetween(2020, 2030),
            'tenant_id' => Tenant::factory(),
        ];
    }

    public function forTile(Tile $tile): static
    {
        return $this->state(fn (array $attributes) => [
            'tile_id' => $tile->id,
            'tenant_id' => $tile->tenant_id,
        ]);
    }
}
