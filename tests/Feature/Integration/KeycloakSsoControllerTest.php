<?php

namespace Tests\Feature\Integration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
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

        $provider = Mockery::mock(AbstractProvider::class);
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

    public function test_callback_aborts_when_sso_disabled(): void
    {
        config(['integrations.keycloak_sso.enabled' => false]);

        $response = $this->get(route('auth.keycloak.callback'));

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

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('user')
            ->once()
            ->andThrow(new InvalidStateException);

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

    public function test_callback_denies_login_and_does_not_reactivate_inactive_user_matched_by_email(): void
    {
        config(['integrations.keycloak_sso.enabled' => true]);
        config(['integrations.keycloak_sso.role_mapping' => ['admin' => 'Admin']]);

        $user = User::factory()->create([
            'email' => 'deactivated@example.com',
            'keycloak_id' => null,
            'is_active' => false,
            'is_admin' => false,
        ]);

        $this->mockSocialiteCallback([
            'id' => 'kc-deactivated',
            'email' => 'deactivated@example.com',
            // If role sync ran despite the denial, this would flip is_admin to
            // true — asserting it stays false proves the panel-access check
            // runs (and rejects) before syncRolesFromToken().
            'roles' => ['admin'],
        ]);

        $response = $this->get(route('auth.keycloak.callback'));

        $response->assertRedirect('/admin/login');
        $this->assertGuest();
        $this->assertFalse($user->fresh()->is_active);
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_callback_denies_login_when_email_match_is_not_verified(): void
    {
        config(['integrations.keycloak_sso.enabled' => true]);

        $user = User::factory()->create([
            'email' => 'victim@example.com',
            'keycloak_id' => null,
        ]);

        $this->mockSocialiteCallback([
            'id' => 'kc-attacker',
            'email' => 'victim@example.com',
            'email_verified' => false,
        ]);

        $response = $this->get(route('auth.keycloak.callback'));

        $response->assertRedirect('/admin/login');
        $this->assertGuest();
        $this->assertNull($user->fresh()->keycloak_id);
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
            'email_verified' => $attributes['email_verified'] ?? true,
            'given_name' => $attributes['first_name'] ?? 'Test',
            'family_name' => $attributes['last_name'] ?? 'User',
            'realm_access' => ['roles' => $attributes['roles'] ?? ['editor']],
        ];

        $provider = Mockery::mock(AbstractProvider::class);
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
