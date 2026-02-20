<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    /**
     * Default roles that should exist for each tenant.
     */
    public const DEFAULT_ROLES = [
        'Redakteur',
    ];

    /**
     * Create default roles for a tenant.
     */
    public function createDefaultRolesForTenant(Tenant $tenant): array
    {
        $roles = [];

        foreach (self::DEFAULT_ROLES as $roleName) {
            $roles[] = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);
        }

        return $roles;
    }

    /**
     * Assign a role to a user within a tenant.
     *
     * @throws \InvalidArgumentException If the role doesn't exist for the tenant.
     */
    public function assignRoleInTenant(User $user, string $roleName, Tenant $tenant): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $role = Role::where('name', $roleName)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $role) {
            throw new \InvalidArgumentException(
                "Role '{$roleName}' does not exist for tenant '{$tenant->slug}'."
            );
        }

        $this->removeAllRolesInTenant($user, $tenant);
        $user->assignRole($role);
    }

    /**
     * Remove all roles from a user within a tenant.
     */
    public function removeAllRolesInTenant(User $user, Tenant $tenant): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $tenantRoles = Role::where('tenant_id', $tenant->id)->pluck('name')->toArray();

        foreach ($tenantRoles as $roleName) {
            if ($user->hasRole($roleName)) {
                $user->removeRole($roleName);
            }
        }
    }

    /**
     * Get the user's role name within a tenant.
     */
    public function getUserRoleInTenant(User $user, Tenant $tenant): ?string
    {
        if ($user->is_admin) {
            return 'Admin';
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles');

        return $user->hasRole('Redakteur') ? 'Redakteur' : null;
    }

    /**
     * Check if a user is the last active admin globally.
     */
    public function isLastAdmin(User $user): bool
    {
        if (! $user->is_admin) {
            return false;
        }

        return ! User::where('is_admin', true)
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->exists();
    }
}
