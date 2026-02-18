<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'description' => fake()->optional(0.5)->company(),
            'slug' => fake()->unique()->slug(2),
        ];
    }

    public function withDomain(?string $domain = null): static
    {
        return $this->state(fn (array $attributes) => [
            'domain' => $domain ?? fake()->domainName(),
        ]);
    }

    public function withFrontendUrl(?string $url = null): static
    {
        return $this->state(fn (array $attributes) => [
            'frontend_base_url' => $url ?? 'https://'.fake()->domainName(),
        ]);
    }
}
