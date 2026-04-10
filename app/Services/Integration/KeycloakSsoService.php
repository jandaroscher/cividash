<?php

namespace App\Services\Integration;

use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
     */
    public function findOrCreateUser(SocialiteUser $socialiteUser): User
    {
        $keycloakId = $socialiteUser->id;
        $email = $socialiteUser->email;
        $rawAttributes = $socialiteUser->user ?? [];

        // Priority 1: Match by keycloak_id
        $user = User::where('keycloak_id', $keycloakId)->first();

        if ($user) {
            return $user;
        }

        // Priority 2: Match by email, backfill keycloak_id
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update(['keycloak_id' => $keycloakId, 'is_active' => true]);

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

        // Handle Admin role (is_admin flag, not Spatie)
        if (in_array('Admin', $mappedRoles)) {
            $user->update(['is_admin' => true]);

            return;
        }

        // Handle Redakteur role in default tenant
        if (in_array('Redakteur', $mappedRoles)) {
            $tenant = $user->tenants()->where('slug', 'default')->first()
                ?? Tenant::where('slug', 'default')->first();

            if ($tenant) {
                if (! $user->tenants()->where('tenant_id', $tenant->id)->exists()) {
                    $user->tenants()->syncWithoutDetaching($tenant->id);
                }

                $this->roleService->createDefaultRolesForTenant($tenant);
                $this->roleService->assignRoleInTenant($user, 'Redakteur', $tenant);
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

        return array_unique($roles);
    }
}
