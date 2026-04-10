<?php

namespace Tests\Feature\Integration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class KeycloakSsoControllerTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    public function test_redirect_returns_302_to_keycloak_when_enabled(): void
    {
        config(['integrations.keycloak_sso.enabled' => true]);

        $provider = Mockery::mock(\Laravel\Socialite\Two\AbstractProvider::class);
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('http://localhost:8080/realms/civitas/protocol/openid-connect/auth'));

        Socialite::shouldReceive('driver')
            ->with('keycloak')
            ->once()
            ->andReturn($provider);

        $response = $this->get(route('auth.keycloak.redirect'));

        $response->assertRedirect();
    }

    public function test_redirect_aborts_when_sso_disabled(): void
    {
        config(['integrations.keycloak_sso.enabled' => false]);

        $response = $this->get(route('auth.keycloak.redirect'));

        $response->assertStatus(403);
    }

    public function test_callback_logs_in_existing_user_and_redirects(): void
    {
        config(['integrations.keycloak_sso.enabled' => true]);

        $user = User::factory()->create([
            'email' => 'sso@example.com',
            'keycloak_id' => 'kc-existing',
        ]);

        $socialiteUser = $this->mockSocialiteCallback([
            'id' => 'kc-existing',
            'email' => 'sso@example.com',
            'first_name' => 'SSO',
            'last_name' => 'User',
        ]);

        $response = $this->get(route('auth.keycloak.callback'));

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_creates_new_user_on_first_sso_login(): void
    {
        config(['integrations.keycloak_sso.enabled' => true]);

        $this->mockSocialiteCallback([
            'id' => 'kc-brand-new',
            'email' => 'newuser@example.com',
            'first_name' => 'Brand',
            'last_name' => 'New',
        ]);

        $response = $this->get(route('auth.keycloak.callback'));

        $response->assertRedirect('/admin');
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'keycloak_id' => 'kc-brand-new',
        ]);
        $this->assertAuthenticated();
    }

    public function test_callback_handles_invalid_state_gracefully(): void
    {
        config(['integrations.keycloak_sso.enabled' => true]);

        $provider = Mockery::mock(\Laravel\Socialite\Two\AbstractProvider::class);
        $provider->shouldReceive('user')
            ->once()
            ->andThrow(new \Laravel\Socialite\Two\InvalidStateException);

        Socialite::shouldReceive('driver')
            ->with('keycloak')
            ->once()
            ->andReturn($provider);

        $response = $this->get(route('auth.keycloak.callback'));

        $response->assertRedirect('/admin/login');
    }

    public function test_callback_rejects_inactive_user(): void
    {
        config(['integrations.keycloak_sso.enabled' => true]);

        // Create an inactive user whose is_active won't be changed
        // because the user exists with keycloak_id already set
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'keycloak_id' => 'kc-inactive',
            'is_active' => false,
        ]);

        $this->mockSocialiteCallback([
            'id' => 'kc-inactive',
            'email' => 'inactive@example.com',
        ]);

        $response = $this->get(route('auth.keycloak.callback'));

        // User matched by keycloak_id (no update triggered), canAccessPanel returns false
        $response->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    /**
     * Mock the Socialite callback to return a fake user.
     */
    protected function mockSocialiteCallback(array $attributes): SocialiteUser
    {
        $socialiteUser = new SocialiteUser;
        $socialiteUser->id = $attributes['id'] ?? 'kc-uuid';
        $socialiteUser->email = $attributes['email'] ?? 'test@example.com';
        $socialiteUser->name = trim(($attributes['first_name'] ?? 'Test').' '.($attributes['last_name'] ?? 'User'));
        $socialiteUser->token = 'fake-access-token';
        $socialiteUser->refreshToken = 'fake-refresh-token';
        $socialiteUser->expiresIn = 300;

        $socialiteUser->user = [
            'sub' => $socialiteUser->id,
            'email' => $socialiteUser->email,
            'given_name' => $attributes['first_name'] ?? 'Test',
            'family_name' => $attributes['last_name'] ?? 'User',
            'realm_access' => ['roles' => $attributes['roles'] ?? ['editor']],
        ];

        $provider = Mockery::mock(\Laravel\Socialite\Two\AbstractProvider::class);
        $provider->shouldReceive('user')
            ->once()
            ->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with('keycloak')
            ->once()
            ->andReturn($provider);

        return $socialiteUser;
    }
}
