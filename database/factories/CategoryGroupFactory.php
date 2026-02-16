<?php

namespace Database\Factories;

use App\Models\CategoryGroup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryGroup>
 */
class CategoryGroupFactory extends Factory
{
    protected $model = CategoryGroup::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'title' => ['de' => fake()->words(2, true), 'en' => fake()->words(2, true)],
            'position' => fake()->numberBetween(0, 100),
            'is_filterable' => false,
            'is_color_source' => false,
            'is_active' => true,
            'selection_type' => 'multi',
            'tenant_id' => Tenant::factory(),
        ];
    }

    public function filterable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_filterable' => true,
        ]);
    }

    public function colorSource(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_color_source' => true,
        ]);
    }

    public function singleSelect(): static
    {
        return $this->state(fn (array $attributes) => [
            'selection_type' => 'single',
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
