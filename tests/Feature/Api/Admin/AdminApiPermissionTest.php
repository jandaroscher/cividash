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

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->tenants()->attach($this->tenant->id);
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

    // ========== Token Ability Tests ==========

    public function test_user_with_admin_api_token_can_access(): void
    {
        $token = $this->createTokenForTenant($this->adminUser, $this->tenant, ['admin-api']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tiles', [
                'title' => ['de' => 'Test', 'en' => 'Test'],
            ]);

        $response->assertStatus(201);
    }

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
}
