<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApiPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $adminUser;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);

        $this->adminUser = User::factory()->create(['admin_api_enabled' => true]);
        $this->adminUser->tenants()->attach($this->tenant->id);

        $this->regularUser = User::factory()->create(['admin_api_enabled' => false]);
        $this->regularUser->tenants()->attach($this->tenant->id);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    /**
     * Create a Sanctum token with tenant_id for API requests.
     */
    protected function createTokenForTenant(User $user, Tenant $tenant, array $abilities = ['admin-api']): string
    {
        $token = $user->createToken('test-token', $abilities);
        $token->accessToken->tenant_id = $tenant->id;
        $token->accessToken->save();

        return $token->plainTextToken;
    }

    // ========== User Flag Tests ==========

    public function test_user_without_admin_api_enabled_gets_403(): void
    {
        $token = $this->createTokenForTenant($this->regularUser, $this->tenant, ['admin-api']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tiles', [
                'title' => ['de' => 'Test', 'en' => 'Test'],
            ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Admin API access not enabled for this user.']);
    }

    public function test_user_with_admin_api_enabled_can_access(): void
    {
        $token = $this->createTokenForTenant($this->adminUser, $this->tenant, ['admin-api']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tiles', [
                'title' => ['de' => 'Test', 'en' => 'Test'],
            ]);

        $response->assertStatus(201);
    }

    // ========== Token Ability Tests ==========

    public function test_token_without_admin_api_ability_gets_403(): void
    {
        // Token has 'public-read' but NOT 'admin-api'
        $token = $this->createTokenForTenant($this->adminUser, $this->tenant, ['public-read']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tiles', [
                'title' => ['de' => 'Test', 'en' => 'Test'],
            ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Token does not have admin-api permission.']);
    }

    public function test_token_with_admin_api_ability_can_access(): void
    {
        $token = $this->createTokenForTenant($this->adminUser, $this->tenant, ['admin-api']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tiles', [
                'title' => ['de' => 'Test', 'en' => 'Test'],
            ]);

        $response->assertStatus(201);
    }

    public function test_token_with_wildcard_ability_can_access(): void
    {
        // Wildcard ability should grant all permissions
        $token = $this->createTokenForTenant($this->adminUser, $this->tenant, ['*']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tiles', [
                'title' => ['de' => 'Test', 'en' => 'Test'],
            ]);

        $response->assertStatus(201);
    }

    // ========== Combination Tests ==========

    public function test_regular_user_with_admin_api_token_still_gets_403(): void
    {
        // User has token with admin-api ability but user flag is false
        $token = $this->createTokenForTenant($this->regularUser, $this->tenant, ['admin-api']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tiles', [
                'title' => ['de' => 'Test', 'en' => 'Test'],
            ]);

        // User flag is checked first, so 403 with user message
        $response->assertStatus(403)
            ->assertJson(['message' => 'Admin API access not enabled for this user.']);
    }

    public function test_permission_check_applies_to_all_methods(): void
    {
        $token = $this->createTokenForTenant($this->regularUser, $this->tenant, ['admin-api']);

        // POST
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tiles', ['title' => ['de' => 'Test']])
            ->assertStatus(403);

        // PATCH
        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/admin/tiles/1', ['title' => ['de' => 'Test']])
            ->assertStatus(403);

        // DELETE
        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/admin/tiles/1')
            ->assertStatus(403);
    }
}
