<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Tile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tile>
 */
class TileFactory extends Factory
{
    protected $model = Tile::class;

    public function definition(): array
    {
        $titleDe = fake()->words(3, true);
        $titleEn = fake()->words(3, true);

        return [
            'title' => ['de' => $titleDe, 'en' => $titleEn],
            'description' => ['de' => fake()->paragraph(), 'en' => fake()->paragraph()],
            'slug' => ['de' => Str::slug($titleDe), 'en' => Str::slug($titleEn)],
            'position' => fake()->numberBetween(0, 100),
            'is_public' => true,
            'tenant_id' => Tenant::factory(),
        ];
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }

    public function withIcon(): static
    {
        return $this->state(fn (array $attributes) => [
            'icon' => 'tiles/test-icon.svg',
        ]);
    }

    public function withBackgroundBlocks(?array $blocks = null): static
    {
        return $this->state(fn (array $attributes) => [
            'background_blocks' => $blocks ?? [
                'de' => [
                    ['type' => 'intro-text', 'data' => ['content' => '<p>Test</p>'], 'is_active' => true],
                ],
                'en' => [
                    ['type' => 'intro-text', 'data' => ['content' => '<p>Test</p>'], 'is_active' => true],
                ],
            ],
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
