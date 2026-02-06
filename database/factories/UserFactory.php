<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Provide default attribute values for creating a User model.
     *
     * Attributes:
     * - name: a generated full name.
     * - email: a unique, safe email address.
     * - email_verified_at: the current timestamp.
     * - password: a hashed default password (caches the hashed value in the factory).
     * - remember_token: a random 10-character string.
     * - admin_api_enabled: `false` by default.
     *
     * @return array<string, mixed> An associative array of model attributes and their default values.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'admin_api_enabled' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * Sets the `email_verified_at` attribute to null.
     *
     * @return static The factory instance with the unverified state applied.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Configure the factory to create users with admin API access.
     *
     * @return static A factory state that sets `admin_api_enabled` to `true`.
     */
    public function withAdminApiAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'admin_api_enabled' => true,
        ]);
    }
}
