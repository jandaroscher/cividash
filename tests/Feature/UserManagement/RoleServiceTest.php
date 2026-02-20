<?php

namespace Tests\Feature\UserManagement;

use App\Models\User;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class RoleServiceTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected RoleService $roleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roleService = app(RoleService::class);
    }

    public function test_creates_default_roles_for_tenant(): void
    {
        $tenant = $this->createTenant(['slug' => 'role-test-tenant']);

        foreach (RoleService::DEFAULT_ROLES as $roleName) {
            $this->assertDatabaseHas('roles', [
                'name' => $roleName,
                'tenant_id' => $tenant->id,
            ]);
        }
    }

    public function test_assigns_redakteur_role_to_user_in_tenant(): void
    {
        $tenant = $this->createTenantWithRoles(['slug' => 'assign-role-tenant']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id);

        $this->roleService->assignRoleInTenant($user, 'Redakteur', $tenant);

        $this->assertEquals('Redakteur', $this->roleService->getUserRoleInTenant($user, $tenant));
    }

    public function test_get_user_role_returns_admin_for_admin_user(): void
    {
        $tenant = $this->createTenantWithRoles(['slug' => 'admin-role-tenant']);
        $admin = User::factory()->admin()->create();

        $this->assertEquals('Admin', $this->roleService->getUserRoleInTenant($admin, $tenant));
    }

    public function test_detects_last_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($this->roleService->isLastAdmin($admin));
    }

    public function test_does_not_detect_last_admin_when_multiple_admins_exist(): void
    {
        $admin1 = User::factory()->admin()->create();
        $admin2 = User::factory()->admin()->create();

        $this->assertFalse($this->roleService->isLastAdmin($admin1));
        $this->assertFalse($this->roleService->isLastAdmin($admin2));
    }

    public function test_non_admin_is_not_last_admin(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->roleService->isLastAdmin($user));
    }

    public function test_removes_all_roles_in_tenant(): void
    {
        $tenant = $this->createTenantWithRoles(['slug' => 'remove-roles-tenant']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id);

        $this->roleService->assignRoleInTenant($user, 'Redakteur', $tenant);
        $this->assertEquals('Redakteur', $this->roleService->getUserRoleInTenant($user, $tenant));

        $this->roleService->removeAllRolesInTenant($user, $tenant);
        $this->assertNull($this->roleService->getUserRoleInTenant($user, $tenant));
    }

    public function test_roles_are_tenant_isolated(): void
    {
        $tenantA = $this->createTenantWithRoles(['slug' => 'tenant-a-roles']);
        $tenantB = $this->createTenantWithRoles(['slug' => 'tenant-b-roles']);

        $user = User::factory()->create();
        $user->tenants()->attach([$tenantA->id, $tenantB->id]);

        $this->roleService->assignRoleInTenant($user, 'Redakteur', $tenantA);
        $this->clearPermissionCache();

        $this->roleService->assignRoleInTenant($user, 'Redakteur', $tenantB);
        $this->clearPermissionCache();

        $user->refresh();

        $this->assertEquals('Redakteur', $this->roleService->getUserRoleInTenant($user, $tenantA));
        $this->clearPermissionCache();

        $this->assertEquals('Redakteur', $this->roleService->getUserRoleInTenant($user, $tenantB));
    }
}
