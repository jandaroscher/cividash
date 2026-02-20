<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class TenantUsersApiTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenantWithRoles();
        $this->admin = $this->createAdminUser();
    }

    public function test_admin_can_list_tenant_users(): void
    {
        $this->createUserWithRoleInTenant($this->tenant, 'Redakteur');

        $response = $this->actingAs($this->admin)
            ->getJson("/api/tenants/{$this->tenant->slug}/users");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'first_name', 'last_name', 'email', 'role', 'joined_at'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        // Only redakteur is listed (admin has no tenant_user pivot entry)
        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_redakteur_cannot_list_tenant_users(): void
    {
        $redakteur = $this->createUserWithRoleInTenant($this->tenant, 'Redakteur');

        $response = $this->actingAs($redakteur)
            ->getJson("/api/tenants/{$this->tenant->slug}/users");

        $response->assertForbidden();
    }

    public function test_admin_can_remove_user(): void
    {
        $user = $this->createUserWithRoleInTenant($this->tenant, 'Redakteur');

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/tenants/{$this->tenant->slug}/users/{$user->id}");

        $response->assertOk();

        $this->assertFalse($this->tenant->users()->where('users.id', $user->id)->exists());
    }

    public function test_cannot_remove_admin_from_tenant(): void
    {
        // Attach admin to tenant for this test
        $this->admin->tenants()->attach($this->tenant->id);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/tenants/{$this->tenant->slug}/users/{$this->admin->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user']);
    }

    public function test_non_admin_cannot_access_tenant_users(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->getJson("/api/tenants/{$this->tenant->slug}/users");

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_tenant_users(): void
    {
        $response = $this->getJson("/api/tenants/{$this->tenant->slug}/users");

        $response->assertUnauthorized();
    }
}
