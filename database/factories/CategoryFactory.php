<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'slug' => ['de' => fake()->unique()->words(2, true), 'en' => fake()->unique()->words(2, true)],
            'key' => fake()->unique()->slug(2),
            'position' => fake()->numberBetween(0, 100),
            'is_active' => true,
            'category_group_id' => CategoryGroup::factory(),
            'tenant_id' => Tenant::factory(),
        ];
    }

    public function withColor(?string $color = null): static
    {
        return $this->state(fn (array $attributes) => [
            'color' => $color ?? fake()->hexColor(),
        ]);
    }

    public function withIcon(?string $icon = null): static
    {
        return $this->state(fn (array $attributes) => [
            'icon' => $icon ?? json_encode(['de' => 'categories/test-icon-de.svg', 'en' => 'categories/test-icon-en.svg']),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forGroup(CategoryGroup $group): static
    {
        return $this->state(fn (array $attributes) => [
            'category_group_id' => $group->id,
            'tenant_id' => $group->tenant_id,
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
