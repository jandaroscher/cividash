<?php

namespace Database\Factories;

use App\Models\MetricDefinition;
use App\Models\Tile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetricDefinition>
 */
class MetricDefinitionFactory extends Factory
{
    protected $model = MetricDefinition::class;

    public function definition(): array
    {
        return [
            'tile_id' => Tile::factory(),
            'metric_key' => fake()->unique()->slug(2),
            'label' => ['de' => fake()->words(2, true), 'en' => fake()->words(2, true)],
            'unit' => ['de' => fake()->randomElement(['%', 'kg', 'kWh', 't CO₂']), 'en' => fake()->randomElement(['%', 'kg', 'kWh', 't CO₂'])],
            'indicator_type' => fake()->randomElement(['small', 'big']),
            'is_active' => true,
            'tenant_id' => fn (array $attributes) => Tile::find($attributes['tile_id'])->tenant_id,
        ];
    }

    public function forTile(Tile $tile): static
    {
        return $this->state(fn () => [
            'tile_id' => $tile->id,
            'tenant_id' => $tile->tenant_id,
        ]);
    }
}
