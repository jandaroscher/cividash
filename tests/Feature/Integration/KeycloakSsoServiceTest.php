<?php

namespace Tests\Feature\Integration;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Integration\KeycloakSsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class KeycloakSsoServiceTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected KeycloakSsoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(KeycloakSsoService::class);
    }

    public function test_is_enabled_returns_false_by_default(): void
    {
        config(['integrations.keycloak_sso.enabled' => false]);

        $this->assertFalse($this->service->isEnabled());
    }

    public function test_is_enabled_returns_true_when_configured(): void
    {
        config(['integrations.keycloak_sso.enabled' => true]);

        $this->assertTrue($this->service->isEnabled());
    }

    public function test_finds_existing_user_by_keycloak_id(): void
    {
        $user = User::factory()->create([
            'keycloak_id' => 'kc-uuid-123',
            'email' => 'existing@example.com',
        ]);

        $socialiteUser = $this->makeSocialiteUser([
            'id' => 'kc-uuid-123',
            'email' => 'different@example.com',
            'first_name' => 'Updated',
            'last_name' => 'Name',
        ]);

        $result = $this->service->findOrCreateUser($socialiteUser);

        $this->assertEquals($user->id, $result->id);
    }

    public function test_finds_existing_user_by_email_and_backfills_keycloak_id(): void
    {
        $user = User::factory()->create([
            'email' => 'editor@example.com',
            'keycloak_id' => null,
        ]);

        $socialiteUser = $this->makeSocialiteUser([
            'id' => 'kc-uuid-456',
            'email' => 'editor@example.com',
        ]);

        $result = $this->service->findOrCreateUser($socialiteUser);

        $this->assertEquals($user->id, $result->id);
        $this->assertEquals('kc-uuid-456', $result->fresh()->keycloak_id);
    }

    public function test_creates_new_user_when_no_match_found(): void
    {
        $socialiteUser = $this->makeSocialiteUser([
            'id' => 'kc-uuid-new',
            'email' => 'newuser@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
        ]);

        $result = $this->service->findOrCreateUser($socialiteUser);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'keycloak_id' => 'kc-uuid-new',
            'first_name' => 'New',
            'last_name' => 'User',
            'is_active' => true,
        ]);
    }

    public function test_new_user_is_assigned_to_default_tenant(): void
    {
        Tenant::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default Dashboard']
        );

        $socialiteUser = $this->makeSocialiteUser([
            'id' => 'kc-uuid-tenant',
            'email' => 'tenant-test@example.com',
        ]);

        $result = $this->service->findOrCreateUser($socialiteUser);

        $this->assertTrue(
            $result->tenants()->where('slug', 'default')->exists()
        );
    }

    public function test_maps_admin_role_from_keycloak_token(): void
    {
        $user = User::factory()->create(['keycloak_id' => 'kc-admin', 'is_admin' => false]);

        config(['integrations.keycloak_sso.role_mapping' => [
            'admin' => 'Admin',
            'editor' => 'Redakteur',
        ]]);

        $tokenData = [
            'realm_access' => ['roles' => ['admin', 'default-roles-civitas']],
        ];

        $this->service->syncRolesFromToken($user, $tokenData);

        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_maps_editor_role_from_keycloak_token(): void
    {
        $user = User::factory()->create(['keycloak_id' => 'kc-editor']);
        $tenant = Tenant::where('slug', 'default')->first();

        config(['integrations.keycloak_sso.role_mapping' => [
            'admin' => 'Admin',
            'editor' => 'Redakteur',
        ]]);

        $tokenData = [
            'realm_access' => ['roles' => ['editor', 'default-roles-civitas']],
        ];

        $this->service->syncRolesFromToken($user, $tokenData);

        $user->unsetRelation('roles');
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $this->assertTrue($user->fresh()->hasRole('Redakteur'));
    }

    public function test_extracts_roles_from_resource_access_as_fallback(): void
    {
        $user = User::factory()->create(['keycloak_id' => 'kc-resource']);
        $tenant = Tenant::where('slug', 'default')->first();

        config([
            'integrations.keycloak_sso.role_mapping' => [
                'admin' => 'Admin',
                'editor' => 'Redakteur',
            ],
            'services.keycloak.client_id' => 'cividash-dashboard',
        ]);

        $tokenData = [
            'realm_access' => ['roles' => ['default-roles-civitas']],
            'resource_access' => [
                'cividash-dashboard' => ['roles' => ['editor']],
            ],
        ];

        $this->service->syncRolesFromToken($user, $tokenData);

        $user->unsetRelation('roles');
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $this->assertTrue($user->fresh()->hasRole('Redakteur'));
    }

    public function test_user_marked_active_on_sso_login(): void
    {
        $user = User::factory()->inactive()->create([
            'keycloak_id' => null,
            'email' => 'inactive@example.com',
        ]);

        $socialiteUser = $this->makeSocialiteUser([
            'id' => 'kc-activate',
            'email' => 'inactive@example.com',
        ]);

        $result = $this->service->findOrCreateUser($socialiteUser);

        $this->assertTrue($result->fresh()->is_active);
    }

    /**
     * Create a mock SocialiteUser with the given attributes.
     */
    protected function makeSocialiteUser(array $attributes): SocialiteUser
    {
        $socialiteUser = new SocialiteUser;
        $socialiteUser->id = $attributes['id'] ?? 'kc-uuid-default';
        $socialiteUser->email = $attributes['email'] ?? 'test@example.com';
        $socialiteUser->name = trim(($attributes['first_name'] ?? 'Test').' '.($attributes['last_name'] ?? 'User'));

        $socialiteUser->user = [
            'sub' => $socialiteUser->id,
            'email' => $socialiteUser->email,
            'given_name' => $attributes['first_name'] ?? 'Test',
            'family_name' => $attributes['last_name'] ?? 'User',
        ];

        return $socialiteUser;
    }
}
