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
            'is_active' => true,
            'tenant_id' => Tenant::factory(),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
