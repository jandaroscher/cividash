<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Token Test', 'slug' => 'token-test']);
        $this->user = User::factory()->create();
        $this->user->tenants()->attach($this->tenant->id);
    }

    public function test_token_with_empty_abilities_gets_403_on_admin(): void
    {
        $token = $this->user->createToken('empty-abilities', []);
        $token->accessToken->tenant_id = $this->tenant->id;
        $token->accessToken->save();

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/admin/tiles', ['title' => ['de' => 'Test']]);

        $response->assertForbidden();
    }

    public function test_token_without_admin_api_ability_gets_403(): void
    {
        $token = $this->user->createToken('read-only', ['read']);
        $token->accessToken->tenant_id = $this->tenant->id;
        $token->accessToken->save();

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/admin/tiles', ['title' => ['de' => 'Test']]);

        $response->assertForbidden();
    }

    public function test_revoked_token_gets_401(): void
    {
        $token = $this->user->createToken('revokeable', ['admin-api']);
        $token->accessToken->tenant_id = $this->tenant->id;
        $token->accessToken->save();

        $plainToken = $token->plainTextToken;

        // Revoke the token
        $token->accessToken->delete();

        $response = $this->withToken($plainToken)
            ->getJson('/api/user');

        $response->assertUnauthorized();
    }

    public function test_token_without_tenant_id_gets_400_on_admin(): void
    {
        $token = $this->user->createToken('no-tenant', ['admin-api']);
        // Explicitly NOT setting tenant_id

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/admin/tiles', ['title' => ['de' => 'Test']]);

        $response->assertStatus(400);
    }

    public function test_valid_user_token_can_access_user_endpoint(): void
    {
        $token = $this->user->createToken('user-token', ['*']);

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/user');

        $response->assertOk();
        $response->assertJsonFragment(['email' => $this->user->email]);
    }

    public function test_no_token_gets_401_on_protected_endpoint(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertUnauthorized();
    }

    public function test_invalid_token_format_gets_401(): void
    {
        $response = $this->withToken('not-a-real-token-at-all')
            ->getJson('/api/user');

        $response->assertUnauthorized();
    }

    public function test_wildcard_ability_token_can_access_admin(): void
    {
        $token = $this->user->createToken('wildcard', ['*']);
        $token->accessToken->tenant_id = $this->tenant->id;
        $token->accessToken->save();

        // Send intentionally invalid body — if we get 422, auth/ability/tenant checks passed
        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/admin/tiles', []);

        // 422 proves auth (not 401), ability (not 403), and tenant (not 400) all passed
        $response->assertStatus(422);
    }
}
