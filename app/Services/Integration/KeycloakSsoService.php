<?php

namespace App\Services\Integration;

use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Socialite\Two\User as SocialiteUser;

class KeycloakSsoService
{
    public function __construct(
        protected RoleService $roleService,
    ) {}

    /**
     * Check if Keycloak SSO is enabled via config.
     */
    public function isEnabled(): bool
    {
        return (bool) config('integrations.keycloak_sso.enabled', false);
    }

    /**
     * Find an existing user or create a new one from a Socialite user.
     *
     * Matching order: keycloak_id → email → create new.
     *
     * Once a Keycloak identity is already linked (matched by `keycloak_id`),
     * subsequent logins are trusted on that stable identifier alone.
     * Otherwise — whether linking to an existing account by email or
     * provisioning a brand new one — Keycloak must claim the address as
     * verified (`email_verified`); an unverified claim could otherwise let an
     * attacker take over an unrelated account, or pre-provision a local
     * account (with roles synced from their own token) for an email address
     * they don't control, ready to be reclaimed once the real owner
     * eventually logs in. Linking never reactivates the account — an
     * inactive local user stays inactive and is denied further down the
     * login flow (`canAccessPanel`).
     *
     * @throws InvalidArgumentException When the SSO payload is missing the
     *                                  external id or email (matching on a
     *                                  null identifier could otherwise attach
     *                                  the login to an unrelated local
     *                                  account that happens to have a null
     *                                  keycloak_id/email), or when no
     *                                  existing `keycloak_id` match was found
     *                                  and Keycloak has not verified the
     *                                  email.
     */
    public function findOrCreateUser(SocialiteUser $socialiteUser): User
    {
        $keycloakId = $socialiteUser->id;
        $email = $socialiteUser->email;
        $rawAttributes = $socialiteUser->user ?? [];

        // Reject incomplete payloads before any User lookup: an empty
        // identifier would turn the lookup into a "... IS NULL" query and
        // could match an unrelated account with null fields.
        if (! is_string($keycloakId) || trim($keycloakId) === '') {
            throw new InvalidArgumentException('Keycloak SSO payload is missing the external user id.');
        }

        if (! is_string($email) || trim($email) === '') {
            throw new InvalidArgumentException('Keycloak SSO payload is missing the user email.');
        }

        // Priority 1: Match by keycloak_id
        $user = User::where('keycloak_id', $keycloakId)->first();

        if ($user) {
            return $user;
        }

        // From here on (linking by email, or provisioning a new account),
        // an unverified email is never trusted. Strict comparison: a realm
        // mapper could emit the claim as the string "false", which is
        // truthy in PHP.
        if (($rawAttributes['email_verified'] ?? false) !== true) {
            throw new InvalidArgumentException('Keycloak email is not verified; refusing to link or create a local account for it.');
        }

        // Priority 2: Match by email, backfill keycloak_id
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update(['keycloak_id' => $keycloakId]);

            return $user;
        }

        // Priority 3: Create new user
        return User::create([
            'first_name' => $rawAttributes['given_name'] ?? explode(' ', $socialiteUser->name ?? '')[0] ?? '',
            'last_name' => $rawAttributes['family_name'] ?? explode(' ', $socialiteUser->name ?? '', 2)[1] ?? '',
            'email' => $email,
            'keycloak_id' => $keycloakId,
            'password' => Hash::make(Str::random(64)),
            'is_active' => true,
        ]);
    }

    /**
     * Sync dashboard roles from Keycloak token claims.
     *
     * Extracts roles from realm_access.roles and resource_access.{client_id}.roles,
     * then maps them to dashboard roles via config('integrations.keycloak_sso.role_mapping').
     */
    public function syncRolesFromToken(User $user, array $tokenData): void
    {
        $keycloakRoles = $this->extractKeycloakRoles($tokenData);
        $roleMapping = config('integrations.keycloak_sso.role_mapping', []);

        $mappedRoles = [];
        foreach ($keycloakRoles as $kcRole) {
            if (isset($roleMapping[$kcRole])) {
                $mappedRoles[] = $roleMapping[$kcRole];
            }
        }

        $tenant = $user->tenants()->where('slug', 'default')->first()
            ?? Tenant::where('slug', 'default')->first();

        // Role sync is authoritative: the current token is the single source
        // of truth. Every privilege is reset to "off" first and only granted
        // again when its mapped role is present in this token, so a role that
        // was removed in Keycloak is revoked on the next login rather than
        // lingering and leaving the user over-privileged.

        // Sync Admin status (is_admin flag, not Spatie). Reset to false, then
        // grant only if the Admin role is present in the current token.
        $shouldBeAdmin = in_array('Admin', $mappedRoles, true);
        if ($user->is_admin !== $shouldBeAdmin) {
            $user->update(['is_admin' => $shouldBeAdmin]);
        }

        // Reconcile the Redakteur role in the default tenant regardless of
        // admin status, so stale tenant roles are always removed when the
        // mapped role is no longer present in the token.
        if ($tenant) {
            if (! $user->tenants()->where('tenant_id', $tenant->id)->exists()) {
                $user->tenants()->syncWithoutDetaching($tenant->id);
            }

            $this->roleService->createDefaultRolesForTenant($tenant);

            if (in_array('Redakteur', $mappedRoles, true)) {
                $this->roleService->assignRoleInTenant($user, 'Redakteur', $tenant);
            } else {
                $this->roleService->removeAllRolesInTenant($user, $tenant);
            }
        }
    }

    /**
     * Extract role names from Keycloak token claims.
     *
     * Checks realm_access.roles first, then resource_access.{client_id}.roles as fallback.
     *
     * @return list<string>
     */
    private function extractKeycloakRoles(array $tokenData): array
    {
        $roles = [];

        // Primary: realm-level roles
        if (isset($tokenData['realm_access']['roles']) && is_array($tokenData['realm_access']['roles'])) {
            $roles = $tokenData['realm_access']['roles'];
        }

        // Fallback: client-specific roles
        $clientId = config('services.keycloak.client_id');
        if ($clientId && isset($tokenData['resource_access'][$clientId]['roles']) && is_array($tokenData['resource_access'][$clientId]['roles'])) {
            $roles = array_merge($roles, $tokenData['resource_access'][$clientId]['roles']);
        }

        // CORE convention: client roles are emitted into a flat "groups" claim
        // (oidc-usermodel-client-role-mapper, claim.name=groups). The userinfo
        // response - which Socialite passes to syncRolesFromToken - carries the
        // roles only here, not in realm_access/resource_access. Unmapped values
        // are ignored downstream, so reading this claim is safe across setups.
        if (isset($tokenData['groups']) && is_array($tokenData['groups'])) {
            $roles = array_merge($roles, $tokenData['groups']);
        }

        return array_unique($roles);
    }
}
