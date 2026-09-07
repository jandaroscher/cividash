<?php

namespace Tests\Feature\Integration;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Integration\KeycloakSsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\PermissionRegistrar;
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
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
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
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $this->assertTrue($user->fresh()->hasRole('Redakteur'));
    }

    public function test_maps_admin_role_from_groups_claim(): void
    {
        // CORE's Keycloak emits client roles via a flat "groups" claim
        // (oidc-usermodel-client-role-mapper, claim.name=groups). The userinfo
        // response - which Socialite passes to syncRolesFromToken - carries the
        // role ONLY there, not in realm_access or resource_access. The sync must
        // read the groups claim or admins stay locked out of the panel.
        $user = User::factory()->create(['keycloak_id' => 'kc-groups-admin', 'is_admin' => false]);

        config(['integrations.keycloak_sso.role_mapping' => [
            'admin' => 'Admin',
            'editor' => 'Redakteur',
        ]]);

        $tokenData = [
            'realm_access' => ['roles' => ['default-roles-tst', 'offline_access']],
            'groups' => ['admin'],
        ];

        $this->service->syncRolesFromToken($user, $tokenData);

        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_maps_editor_role_from_groups_claim(): void
    {
        $user = User::factory()->create(['keycloak_id' => 'kc-groups-editor']);
        $tenant = Tenant::where('slug', 'default')->first();

        config(['integrations.keycloak_sso.role_mapping' => [
            'admin' => 'Admin',
            'editor' => 'Redakteur',
        ]]);

        $tokenData = [
            'realm_access' => ['roles' => ['default-roles-tst']],
            'groups' => ['editor'],
        ];

        $this->service->syncRolesFromToken($user, $tokenData);

        $user->unsetRelation('roles');
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $this->assertTrue($user->fresh()->hasRole('Redakteur'));
    }

    public function test_revokes_admin_when_keycloak_role_removed(): void
    {
        $user = User::factory()->create(['keycloak_id' => 'kc-ex-admin', 'is_admin' => true]);

        config(['integrations.keycloak_sso.role_mapping' => [
            'admin' => 'Admin',
            'editor' => 'Redakteur',
        ]]);

        $tokenData = [
            'realm_access' => ['roles' => ['editor', 'default-roles-civitas']],
        ];

        $this->service->syncRolesFromToken($user, $tokenData);

        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_revokes_redakteur_when_keycloak_role_removed(): void
    {
        $user = User::factory()->create(['keycloak_id' => 'kc-ex-editor']);
        $tenant = Tenant::where('slug', 'default')->first();

        // First assign the role
        $this->service->syncRolesFromToken($user, [
            'realm_access' => ['roles' => ['editor']],
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles');
        $this->assertTrue($user->fresh()->hasRole('Redakteur'));

        // Now sync with no matching roles — should revoke
        config(['integrations.keycloak_sso.role_mapping' => [
            'admin' => 'Admin',
            'editor' => 'Redakteur',
        ]]);

        $this->service->syncRolesFromToken($user, [
            'realm_access' => ['roles' => ['default-roles-civitas']],
        ]);

        $user->unsetRelation('roles');
        $this->assertFalse($user->fresh()->hasRole('Redakteur'));
    }

    public function test_rejects_payload_with_missing_id_and_does_not_match_null_keycloak_id_user(): void
    {
        // A pre-existing account with a null keycloak_id must NOT be matched
        // by an incomplete SSO payload (which would produce a "... IS NULL" query).
        $existing = User::factory()->create([
            'keycloak_id' => null,
            'email' => 'orphan@example.com',
        ]);

        $socialiteUser = $this->makeSocialiteUser([
            'id' => '',
            'email' => 'attacker@example.com',
        ]);

        try {
            $this->service->findOrCreateUser($socialiteUser);
            $this->fail('Expected InvalidArgumentException for missing external id.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('external user id', $e->getMessage());
        }

        // The orphan account is untouched: its keycloak_id stays null.
        $this->assertNull($existing->fresh()->keycloak_id);
    }

    public function test_rejects_payload_with_missing_email_and_does_not_match_null_email_user(): void
    {
        $socialiteUser = $this->makeSocialiteUser([
            'id' => 'kc-uuid-no-email',
            'email' => '',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user email');

        $this->service->findOrCreateUser($socialiteUser);
    }

    public function test_revokes_redakteur_for_user_who_also_loses_admin_role(): void
    {
        // Regression: previously the early return on admin skipped tenant role
        // reconciliation, so an ex-admin kept a stale Redakteur role.
        $user = User::factory()->create(['keycloak_id' => 'kc-admin-editor', 'is_admin' => true]);
        $tenant = Tenant::where('slug', 'default')->first();

        config(['integrations.keycloak_sso.role_mapping' => [
            'admin' => 'Admin',
            'editor' => 'Redakteur',
        ]]);

        // First login: both admin and editor roles present.
        $this->service->syncRolesFromToken($user, [
            'realm_access' => ['roles' => ['admin', 'editor']],
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles');
        $this->assertTrue($user->fresh()->is_admin);
        $this->assertTrue($user->fresh()->hasRole('Redakteur'));

        // Second login: all privileged roles removed in Keycloak.
        $this->service->syncRolesFromToken($user, [
            'realm_access' => ['roles' => ['default-roles-civitas']],
        ]);

        $user->unsetRelation('roles');
        $this->assertFalse($user->fresh()->is_admin);
        $this->assertFalse($user->fresh()->hasRole('Redakteur'));
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
