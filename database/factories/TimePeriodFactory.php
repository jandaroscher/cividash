<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimePeriod>
 */
class TimePeriodFactory extends Factory
{
    protected $model = TimePeriod::class;

    public function definition(): array
    {
        $year = (string) fake()->numberBetween(2020, 2030);

        return [
            'tile_id' => Tile::factory(),
            'granularity' => 'year',
            'period_key' => $year,
            'label' => $year,
            'sort' => 0,
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

    public function year(int $year): static
    {
        return $this->state(fn (array $attributes) => [
            'granularity' => 'year',
            'period_key' => (string) $year,
            'label' => (string) $year,
        ]);
    }

    public function quarter(int $year, int $quarter): static
    {
        return $this->state(fn (array $attributes) => [
            'granularity' => 'quarter',
            'period_key' => "{$year}-Q{$quarter}",
            'label' => "Q{$quarter} {$year}",
        ]);
    }

    public function month(int $year, int $month): static
    {
        $monthPadded = str_pad($month, 2, '0', STR_PAD_LEFT);

        return $this->state(fn (array $attributes) => [
            'granularity' => 'month',
            'period_key' => "{$year}-{$monthPadded}",
            'label' => null,
        ]);
    }
}
