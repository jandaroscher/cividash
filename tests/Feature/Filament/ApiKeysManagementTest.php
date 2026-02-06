<?php

namespace Tests\Feature\Filament;

use App\Models\Tenant;
use App\Models\User;
use App\Services\ApiTokenService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiKeysManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected User $adminUser;

    protected User $regularUser;

    protected ApiTokenService $tokenService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenants
        $this->tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        // Create users with different permissions
        $this->adminUser = User::factory()->create(['admin_api_enabled' => true]);
        $this->adminUser->tenants()->attach([$this->tenantA->id, $this->tenantB->id]);

        $this->regularUser = User::factory()->create(['admin_api_enabled' => false]);
        $this->regularUser->tenants()->attach([$this->tenantA->id, $this->tenantB->id]);

        // Instantiate the service
        $this->tokenService = new ApiTokenService;
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    // ========== Listing Tests (Tenant-Scoped) ==========

    public function test_listing_returns_only_tokens_for_active_tenant(): void
    {
        // Create tokens for Tenant A
        $tokenA1 = $this->adminUser->createToken('Token A1', ['public-read']);
        $tokenA1->accessToken->tenant_id = $this->tenantA->id;
        $tokenA1->accessToken->save();

        $tokenA2 = $this->regularUser->createToken('Token A2', ['public-read']);
        $tokenA2->accessToken->tenant_id = $this->tenantA->id;
        $tokenA2->accessToken->save();

        // Create tokens for Tenant B
        $tokenB1 = $this->adminUser->createToken('Token B1', ['admin-api']);
        $tokenB1->accessToken->tenant_id = $this->tenantB->id;
        $tokenB1->accessToken->save();

        // List tokens for Tenant A
        $tokensA = $this->tokenService->listForTenant($this->tenantA);

        $this->assertCount(2, $tokensA);
        $this->assertTrue($tokensA->pluck('name')->contains('Token A1'));
        $this->assertTrue($tokensA->pluck('name')->contains('Token A2'));
        $this->assertFalse($tokensA->pluck('name')->contains('Token B1'));

        // List tokens for Tenant B
        $tokensB = $this->tokenService->listForTenant($this->tenantB);

        $this->assertCount(1, $tokensB);
        $this->assertTrue($tokensB->pluck('name')->contains('Token B1'));
    }

    public function test_listing_excludes_tokens_without_tenant_id(): void
    {
        // Create token WITH tenant_id
        $tokenWithTenant = $this->adminUser->createToken('With Tenant', ['public-read']);
        $tokenWithTenant->accessToken->tenant_id = $this->tenantA->id;
        $tokenWithTenant->accessToken->save();

        // Create token WITHOUT tenant_id (legacy/orphan)
        $tokenWithoutTenant = $this->adminUser->createToken('Without Tenant', ['public-read']);
        // tenant_id is NULL by default

        // Listing should only return the token with tenant_id
        $tokens = $this->tokenService->listForTenant($this->tenantA);

        $this->assertCount(1, $tokens);
        $this->assertEquals('With Tenant', $tokens->first()->name);
    }

    // ========== Create Tests ==========

    public function test_create_sets_tenant_id_correctly(): void
    {
        $newToken = $this->tokenService->createForTenant(
            $this->adminUser,
            $this->tenantA,
            'My API Token',
            ['public-read']
        );

        $this->assertNotNull($newToken->plainTextToken);
        $this->assertEquals($this->tenantA->id, $newToken->accessToken->tenant_id);
        $this->assertEquals('My API Token', $newToken->accessToken->name);
    }

    public function test_create_persists_abilities_correctly(): void
    {
        // Test public-read only
        $token1 = $this->tokenService->createForTenant(
            $this->adminUser,
            $this->tenantA,
            'Public Token',
            ['public-read']
        );

        $this->assertEquals(['public-read'], $token1->accessToken->abilities);

        // Test admin-api only
        $token2 = $this->tokenService->createForTenant(
            $this->adminUser,
            $this->tenantA,
            'Admin Token',
            ['admin-api']
        );

        $this->assertEquals(['admin-api'], $token2->accessToken->abilities);

        // Test both abilities
        $token3 = $this->tokenService->createForTenant(
            $this->adminUser,
            $this->tenantA,
            'Full Token',
            ['admin-api', 'public-read']
        );

        $this->assertContains('admin-api', $token3->accessToken->abilities);
        $this->assertContains('public-read', $token3->accessToken->abilities);
    }

    public function test_create_admin_api_token_requires_admin_api_enabled_flag(): void
    {
        // Regular user (admin_api_enabled = false) should NOT be able to create admin-api token
        $this->expectException(ValidationException::class);

        $this->tokenService->createForTenant(
            $this->regularUser,
            $this->tenantA,
            'Admin Token',
            ['admin-api']
        );
    }

    public function test_create_admin_api_token_succeeds_with_admin_api_enabled_flag(): void
    {
        // Admin user (admin_api_enabled = true) CAN create admin-api token
        $token = $this->tokenService->createForTenant(
            $this->adminUser,
            $this->tenantA,
            'Admin Token',
            ['admin-api']
        );

        $this->assertNotNull($token->plainTextToken);
        $this->assertEquals(['admin-api'], $token->accessToken->abilities);
    }

    public function test_create_public_read_token_allowed_for_any_user(): void
    {
        // Regular user CAN create public-read token
        $token = $this->tokenService->createForTenant(
            $this->regularUser,
            $this->tenantA,
            'Public Token',
            ['public-read']
        );

        $this->assertNotNull($token->plainTextToken);
        $this->assertEquals(['public-read'], $token->accessToken->abilities);
    }

    public function test_create_rejects_invalid_abilities(): void
    {
        $this->expectException(ValidationException::class);

        $this->tokenService->createForTenant(
            $this->adminUser,
            $this->tenantA,
            'Invalid Token',
            ['unknown-ability']
        );
    }

    public function test_create_rejects_empty_abilities(): void
    {
        $this->expectException(ValidationException::class);

        $this->tokenService->createForTenant(
            $this->adminUser,
            $this->tenantA,
            'Empty Token',
            []
        );
    }

    // ========== Revoke Tests ==========

    public function test_revoke_deletes_token_for_active_tenant(): void
    {
        // Create a token for Tenant A
        $newToken = $this->adminUser->createToken('To Revoke', ['public-read']);
        $newToken->accessToken->tenant_id = $this->tenantA->id;
        $newToken->accessToken->save();

        $tokenId = $newToken->accessToken->id;

        // Revoke it
        $this->tokenService->revokeForTenant($newToken->accessToken, $this->tenantA);

        // Token should be deleted
        $this->assertNull(PersonalAccessToken::find($tokenId));
    }

    public function test_revoke_prevents_cross_tenant_deletion(): void
    {
        // Create a token for Tenant B
        $tokenB = $this->adminUser->createToken('Tenant B Token', ['public-read']);
        $tokenB->accessToken->tenant_id = $this->tenantB->id;
        $tokenB->accessToken->save();

        // Attempt to revoke it from Tenant A context should fail
        $this->expectException(\InvalidArgumentException::class);

        $this->tokenService->revokeForTenant($tokenB->accessToken, $this->tenantA);
    }

    public function test_revoked_token_cannot_be_used_for_api_access(): void
    {
        // Create a valid token
        $newToken = $this->tokenService->createForTenant(
            $this->adminUser,
            $this->tenantA,
            'API Token',
            ['admin-api']
        );

        $plainTextToken = $newToken->plainTextToken;

        // Verify token works before revocation
        $responseBefore = $this->withHeader('Authorization', "Bearer {$plainTextToken}")
            ->postJson('/api/admin/tiles', [
                'title' => ['de' => 'Test', 'en' => 'Test'],
            ]);

        $responseBefore->assertStatus(201);

        // Revoke the token
        $this->tokenService->revokeForTenant($newToken->accessToken, $this->tenantA);

        // Verify token no longer works after revocation
        // Note: Returns 401 (unauthenticated) or 400 (missing tenant context) depending on middleware order
        // Both indicate the token is no longer valid
        $responseAfter = $this->withHeader('Authorization', "Bearer {$plainTextToken}")
            ->postJson('/api/admin/tiles', [
                'title' => ['de' => 'Test 2', 'en' => 'Test 2'],
            ]);

        // Token is revoked, so request must fail (not 201 success)
        $this->assertContains($responseAfter->status(), [400, 401], 'Revoked token should be rejected');
    }

    // ========== Edge Cases ==========

    public function test_listing_returns_tokens_from_multiple_users_in_same_tenant(): void
    {
        // Both users create tokens for Tenant A
        $token1 = $this->adminUser->createToken('Admin Token', ['admin-api']);
        $token1->accessToken->tenant_id = $this->tenantA->id;
        $token1->accessToken->save();

        $token2 = $this->regularUser->createToken('Regular Token', ['public-read']);
        $token2->accessToken->tenant_id = $this->tenantA->id;
        $token2->accessToken->save();

        // Listing should include both
        $tokens = $this->tokenService->listForTenant($this->tenantA);

        $this->assertCount(2, $tokens);

        // Verify different tokenable (owners)
        $tokenables = $tokens->pluck('tokenable_id')->unique();
        $this->assertCount(2, $tokenables);
    }

    public function test_listing_includes_token_metadata(): void
    {
        $newToken = $this->adminUser->createToken('Metadata Test', ['public-read']);
        $newToken->accessToken->tenant_id = $this->tenantA->id;
        $newToken->accessToken->last_used_at = now();
        $newToken->accessToken->save();

        $tokens = $this->tokenService->listForTenant($this->tenantA);
        $token = $tokens->first();

        $this->assertEquals('Metadata Test', $token->name);
        $this->assertNotNull($token->created_at);
        $this->assertNotNull($token->last_used_at);
        $this->assertEquals(['public-read'], $token->abilities);
    }
}
