<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ManageApiKeys;
use App\Filament\Pages\ManageBranding;
use App\Filament\Pages\ManageFooter;
use App\Filament\Pages\ManageGeneral;
use App\Filament\Pages\ManageNavigation;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTenantWithRoles();
        $this->admin = $this->createAdminUser();
        $this->actingAs($this->admin);
        Filament::setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
        Filament::setTenant(null);
        parent::tearDown();
    }

    public function test_admin_can_access_user_resource(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(UserResource::canAccess());
    }

    public function test_redakteur_cannot_access_user_resource(): void
    {
        $redakteur = $this->createUserWithRoleInTenant($this->tenant, 'Redakteur');
        $this->actingAs($redakteur);

        $this->assertFalse(UserResource::canAccess());
    }

    public function test_can_list_users(): void
    {
        $redakteur = $this->createUserWithRoleInTenant($this->tenant, 'Redakteur');

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->assertCanSeeTableRecords([$this->admin, $redakteur])
            ->assertSuccessful();
    }

    public function test_user_list_shows_users_from_other_tenants(): void
    {
        $otherTenant = $this->createTenantWithRoles([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
        ]);
        $otherUser = $this->createUserWithRoleInTenant($otherTenant, 'Redakteur');

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->assertCanSeeTableRecords([$this->admin, $otherUser])
            ->assertSuccessful();
    }

    public function test_can_search_users_by_email(): void
    {
        $redakteur = $this->createUserWithRoleInTenant($this->tenant, 'Redakteur');

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->searchTable($redakteur->email)
            ->assertCanSeeTableRecords([$redakteur])
            ->assertCanNotSeeTableRecords([$this->admin]);
    }

    public function test_can_search_users_by_last_name(): void
    {
        $redakteur = $this->createUserWithRoleInTenant(
            $this->tenant,
            'Redakteur',
            ['last_name' => 'Einzigartig']
        );

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->searchTable('Einzigartig')
            ->assertCanSeeTableRecords([$redakteur]);
    }

    public function test_can_create_user(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'email' => 'max@example.com',
                'password' => 'password123',
                'is_active' => true,
                'role' => 'Redakteur',
                'dashboard_assignments' => [$this->tenant->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
            'is_active' => true,
        ]);
    }

    public function test_can_create_admin_user(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@example.com',
                'password' => 'password123',
                'is_active' => true,
                'role' => 'Admin',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $newAdmin = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($newAdmin);
        $this->assertTrue($newAdmin->is_admin);
    }

    public function test_can_edit_user(): void
    {
        $user = $this->createUserWithRoleInTenant($this->tenant, 'Redakteur');

        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'first_name' => 'Updated',
                'last_name' => 'Name',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertEquals('Updated', $user->first_name);
        $this->assertEquals('Name', $user->last_name);
    }

    public function test_can_toggle_user_active_status(): void
    {
        $user = $this->createUserWithRoleInTenant($this->tenant, 'Redakteur');
        $this->assertTrue($user->is_active);

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->assertCanRenderTableColumn('is_active')
            ->call('updateTableColumnState', 'is_active', $user->getRouteKey(), false);

        $user->refresh();
        $this->assertFalse($user->is_active);
    }

    public function test_cannot_deactivate_self(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->call('updateTableColumnState', 'is_active', $this->admin->getRouteKey(), false)
            ->assertNotified(__('filament.resources.user.messages.cannot_deactivate_self'));

        $this->admin->refresh();
        $this->assertTrue($this->admin->is_active);
    }

    public function test_can_delete_user(): void
    {
        $user = $this->createUserWithRoleInTenant($this->tenant, 'Redakteur');

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->callTableAction('delete', $user);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_cannot_delete_last_admin(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->callTableAction('delete', $this->admin);

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_can_bulk_delete_users(): void
    {
        $user1 = $this->createUserWithRoleInTenant($this->tenant, 'Redakteur');
        $user2 = $this->createUserWithRoleInTenant($this->tenant, 'Redakteur');

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->callTableBulkAction('delete', [$user1, $user2]);

        $this->assertDatabaseMissing('users', ['id' => $user1->id]);
        $this->assertDatabaseMissing('users', ['id' => $user2->id]);
    }

    public function test_filter_by_role(): void
    {
        $redakteur = $this->createUserWithRoleInTenant($this->tenant, 'Redakteur');

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->filterTable('role', 'Admin')
            ->assertCanSeeTableRecords([$this->admin])
            ->assertCanNotSeeTableRecords([$redakteur]);
    }

    public function test_filter_by_role_redakteur(): void
    {
        $redakteur = $this->createUserWithRoleInTenant($this->tenant, 'Redakteur');

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->filterTable('role', 'Redakteur')
            ->assertCanSeeTableRecords([$redakteur])
            ->assertCanNotSeeTableRecords([$this->admin]);
    }

    public function test_filter_by_active_status(): void
    {
        $inactive = $this->createUserWithRoleInTenant(
            $this->tenant,
            'Redakteur',
            ['is_active' => false]
        );

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$this->admin])
            ->assertCanNotSeeTableRecords([$inactive]);
    }

    public function test_inactive_user_cannot_access_panel(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $panel = app(\Filament\Panel::class);
        $this->assertFalse($user->canAccessPanel($panel));
    }

    public function test_active_user_can_access_panel(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $panel = app(\Filament\Panel::class);
        $this->assertTrue($user->canAccessPanel($panel));
    }

    public function test_user_full_name_attribute(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
        ]);

        $this->assertEquals('Max Mustermann', $user->full_name);
    }

    public function test_create_redakteur_only_assigns_selected_tenants(): void
    {
        $otherTenant = $this->createTenantWithRoles([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
        ]);

        Livewire::actingAs($this->admin)
            ->test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Redakteur',
                'last_name' => 'Test',
                'email' => 'redakteur@example.com',
                'password' => 'password123',
                'is_active' => true,
                'role' => 'Redakteur',
                'dashboard_assignments' => [$otherTenant->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $newUser = User::where('email', 'redakteur@example.com')->first();
        $this->assertNotNull($newUser);

        $tenantIds = $newUser->tenants()->pluck('tenants.id')->toArray();

        // Should have ONLY the explicitly selected tenant
        $this->assertContains($otherTenant->id, $tenantIds);
        $this->assertCount(1, $tenantIds, 'Redakteur should only be assigned to explicitly selected tenants');

        // Admin's current tenant should NOT be auto-added
        $this->assertNotContains($this->tenant->id, $tenantIds);

        // default_tenant_id should be set to the first assigned tenant
        $this->assertEquals($otherTenant->id, $newUser->default_tenant_id);
    }

    public function test_redakteur_cannot_access_settings_pages(): void
    {
        $redakteur = $this->createUserWithRoleInTenant($this->tenant, 'Redakteur');
        $this->actingAs($redakteur);

        $this->assertFalse(ManageGeneral::canAccess());
        $this->assertFalse(ManageBranding::canAccess());
        $this->assertFalse(ManageNavigation::canAccess());
        $this->assertFalse(ManageFooter::canAccess());
        $this->assertFalse(ManageApiKeys::canAccess());
    }

    public function test_admin_can_access_settings_pages(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(ManageGeneral::canAccess());
        $this->assertTrue(ManageBranding::canAccess());
        $this->assertTrue(ManageNavigation::canAccess());
        $this->assertTrue(ManageFooter::canAccess());
        $this->assertTrue(ManageApiKeys::canAccess());
    }

    public function test_admin_can_access_settings_in_other_tenant(): void
    {
        $otherTenant = $this->createTenantWithRoles([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
        ]);

        // Switch Filament context to the other tenant
        Filament::setTenant($otherTenant);
        $this->actingAs($this->admin);

        // Admin should still see settings because is_admin=true
        $this->assertTrue(UserResource::canAccess());
        $this->assertTrue(ManageGeneral::canAccess());
        $this->assertTrue(ManageBranding::canAccess());
        $this->assertTrue(ManageNavigation::canAccess());
        $this->assertTrue(ManageFooter::canAccess());
        $this->assertTrue(ManageApiKeys::canAccess());
    }
}
