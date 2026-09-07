<?php

namespace Tests\Concerns;

use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Filament\Facades\Filament;
use Spatie\Permission\PermissionRegistrar;

trait InteractsWithTenancy
{
    /**
     * Create a Tenant model for tests with sensible defaults.
     */
    protected function createTenant(array $attributes = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ], $attributes));
    }

    /**
     * Set the current Filament tenant context for tests.
     */
    protected function setTenantContext(Tenant $tenant): void
    {
        Filament::setTenant($tenant);
    }

    /**
     * Execute the given callback under the provided tenant context and restore the previous tenant afterwards.
     */
    protected function withTenant(Tenant $tenant, callable $callback)
    {
        $previous = Filament::getTenant();
        try {
            Filament::setTenant($tenant);

            return $callback();
        } finally {
            if ($previous) {
                Filament::setTenant($previous);
            } else {
                Filament::setTenant(null);
            }
        }
    }

    /**
     * Run the given callback with the Tenant model's 'tenant' global scope disabled.
     */
    protected function withoutTenantScope(callable $callback)
    {
        $builder = Tenant::withoutGlobalScope('tenant');

        return $callback($builder);
    }

    /**
     * Assign a role to a user within a tenant.
     */
    protected function assignRoleInTenant(User $user, Tenant $tenant, string $role): void
    {
        $roleService = app(RoleService::class);

        if (! $user->tenants()->where('tenant_id', $tenant->id)->exists()) {
            $user->tenants()->attach($tenant->id);
        }

        $roleService->assignRoleInTenant($user, $role, $tenant);
    }

    /**
     * Create an admin user (is_admin=true, no tenant pivot entries needed).
     */
    protected function createAdminUser(array $attributes = []): User
    {
        return User::factory()->admin()->create($attributes);
    }

    /**
     * Create a user and assign them a role in the specified tenant.
     *
     * For 'Admin' role, sets is_admin=true instead of using Spatie.
     */
    protected function createUserWithRoleInTenant(Tenant $tenant, string $role, array $attributes = []): User
    {
        if ($role === 'Admin') {
            return User::factory()->admin()->create($attributes);
        }

        $user = User::factory()->create($attributes);
        $user->tenants()->attach($tenant->id);
        app(RoleService::class)->createDefaultRolesForTenant($tenant);
        app(RoleService::class)->assignRoleInTenant($user, $role, $tenant);

        return $user;
    }

    /**
     * Login as a user with a specific role in a tenant context.
     */
    protected function actingAsUserInTenant(User $user, Tenant $tenant, ?string $role = null): static
    {
        Filament::setTenant($tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        if ($role) {
            $this->assignRoleInTenant($user, $tenant, $role);
        }

        $this->actingAs($user);

        return $this;
    }

    /**
     * Clear the Spatie permission cache.
     */
    protected function clearPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Create a tenant with default roles set up.
     */
    protected function createTenantWithRoles(array $attributes = []): Tenant
    {
        return $this->createTenant($attributes);
    }
}
