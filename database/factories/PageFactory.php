<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $titleDe = fake()->words(3, true);
        $titleEn = fake()->words(3, true);

        return [
            'title' => ['de' => $titleDe, 'en' => $titleEn],
            'slug' => ['de' => Str::slug($titleDe), 'en' => Str::slug($titleEn)],
            'blocks' => ['de' => [], 'en' => []],
            'layout' => 'default',
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

    public function withBlocks(array $blocks): static
    {
        return $this->state(fn (array $attributes) => [
            'blocks' => $blocks,
        ]);
    }

    public function withMeta(?string $title = null, ?string $description = null): static
    {
        return $this->state(fn (array $attributes) => [
            'meta_title' => ['de' => $title ?? fake()->sentence(), 'en' => $title ?? fake()->sentence()],
            'meta_description' => ['de' => $description ?? fake()->paragraph(), 'en' => $description ?? fake()->paragraph()],
        ]);
    }

    public function landingpage(): static
    {
        return $this->state(fn (array $attributes) => [
            'layout' => 'landingpage',
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
