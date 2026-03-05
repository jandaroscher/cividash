<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    public function test_user_can_get_profile(): void
    {
        $tenant = $this->createTenant();
        $user = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'locale' => 'de',
        ]);
        $user->tenants()->attach($tenant->id);

        $response = $this->actingAs($user)
            ->getJson('/api/me');

        $response->assertOk()
            ->assertJson([
                'id' => $user->id,
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
                'locale' => 'de',
            ])
            ->assertJsonStructure([
                'id',
                'first_name',
                'last_name',
                'email',
                'locale',
                'email_verified_at',
                'tenants',
                'default_tenant_id',
            ]);
    }

    public function test_profile_includes_tenants(): void
    {
        $tenant = $this->createTenant(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id);

        $response = $this->actingAs($user)
            ->getJson('/api/me');

        $response->assertOk();

        // User should have at least the test tenant (may also have default tenant)
        $tenants = collect($response->json('tenants'));
        $this->assertTrue(
            $tenants->contains(fn ($t) => $t['slug'] === 'test-tenant' && $t['name'] === 'Test Tenant'),
            'Response should include the test tenant'
        );
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'locale' => 'de',
        ]);

        $response = $this->actingAs($user)
            ->patchJson('/api/me', [
                'first_name' => 'New',
                'last_name' => 'Surname',
                'locale' => 'en',
            ]);

        $response->assertOk()
            ->assertJson([
                'user' => [
                    'first_name' => 'New',
                    'last_name' => 'Surname',
                    'locale' => 'en',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'New',
            'last_name' => 'Surname',
            'locale' => 'en',
        ]);
    }

    public function test_profile_update_fails_without_first_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patchJson('/api/me', [
                'last_name' => 'User',
                'locale' => 'en',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name']);
    }

    public function test_profile_update_rejects_invalid_locale(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patchJson('/api/me', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'locale' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['locale']);
    }

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword1!'),
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/me/password', [
                'current_password' => 'oldpassword1!',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertOk()
            ->assertJson(['message' => __('profile.password_updated')]);

        // Verify password was changed
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_password_update_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correctpassword'),
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/me/password', [
                'current_password' => 'wrongpassword',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_update_requires_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword1!'),
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/me/password', [
                'current_password' => 'oldpassword1!',
                'password' => 'newpassword123',
                'password_confirmation' => 'differentpassword',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_update_rejects_too_short_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword1!'),
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/me/password', [
                'current_password' => 'oldpassword1!',
                'password' => 'short1!',
                'password_confirmation' => 'short1!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertUnauthorized();
    }
}
