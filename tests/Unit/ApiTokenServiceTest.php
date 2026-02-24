<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    private ApiTokenService $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ApiTokenService;
        $this->tenant = Tenant::where('slug', 'default')->first();
    }

    public function test_list_for_tenant_returns_only_own_tokens(): void
    {
        $tenantB = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other']);

        $user = User::factory()->create();

        // Create tokens for each tenant
        $tokenA = $user->createToken('Token A', ['public-read']);
        $tokenA->accessToken->tenant_id = $this->tenant->id;
        $tokenA->accessToken->save();

        $tokenB = $user->createToken('Token B', ['public-read']);
        $tokenB->accessToken->tenant_id = $tenantB->id;
        $tokenB->accessToken->save();

        $result = $this->service->listForTenant($this->tenant);

        $this->assertCount(1, $result);
        $this->assertEquals('Token A', $result->first()->name);
    }

    public function test_list_for_tenant_excludes_null_tenant_tokens(): void
    {
        $user = User::factory()->create();

        // Create a token with tenant_id
        $tenantToken = $user->createToken('Tenant Token', ['public-read']);
        $tenantToken->accessToken->tenant_id = $this->tenant->id;
        $tenantToken->accessToken->save();

        // Create a token without tenant_id (null)
        $user->createToken('Null Token', ['public-read']);

        $result = $this->service->listForTenant($this->tenant);

        $this->assertCount(1, $result);
        $this->assertEquals('Tenant Token', $result->first()->name);
    }

    public function test_revoke_for_tenant_deletes_token(): void
    {
        $user = User::factory()->create();
        $newToken = $user->createToken('To Delete', ['public-read']);
        $newToken->accessToken->tenant_id = $this->tenant->id;
        $newToken->accessToken->save();

        $token = PersonalAccessToken::find($newToken->accessToken->id);

        $this->service->revokeForTenant($token, $this->tenant);

        $this->assertNull(PersonalAccessToken::find($token->id));
    }

    public function test_revoke_throws_for_cross_tenant_token(): void
    {
        $tenantB = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other']);

        $user = User::factory()->create();
        $newToken = $user->createToken('Cross Tenant', ['public-read']);
        $newToken->accessToken->tenant_id = $tenantB->id;
        $newToken->accessToken->save();

        $token = PersonalAccessToken::find($newToken->accessToken->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot revoke token: Token does not belong to the specified tenant.');

        $this->service->revokeForTenant($token, $this->tenant);
    }

    public function test_available_abilities_returns_all(): void
    {
        $abilities = $this->service->getAvailableAbilities();

        $this->assertArrayHasKey('public-read', $abilities);
        $this->assertArrayHasKey('admin-api', $abilities);
        $this->assertCount(2, $abilities);
    }

    public function test_create_validates_empty_abilities(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service->createForTenant($user, $this->tenant, 'Test Token', []);
    }

    public function test_create_validates_invalid_abilities(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service->createForTenant($user, $this->tenant, 'Test Token', ['invalid']);
    }

    public function test_create_for_tenant_succeeds_with_valid_abilities(): void
    {
        $user = User::factory()->create();

        $newToken = $this->service->createForTenant($user, $this->tenant, 'Valid Token', ['public-read', 'admin-api']);

        $this->assertNotEmpty($newToken->plainTextToken);
        $this->assertEquals($this->tenant->id, $newToken->accessToken->tenant_id);
        $this->assertEquals('Valid Token', $newToken->accessToken->name);
    }

    public function test_list_for_tenant_orders_by_newest_first(): void
    {
        $user = User::factory()->create();

        $first = $user->createToken('First', ['public-read']);
        $first->accessToken->tenant_id = $this->tenant->id;
        $first->accessToken->created_at = now()->subHour();
        $first->accessToken->save();

        $second = $user->createToken('Second', ['public-read']);
        $second->accessToken->tenant_id = $this->tenant->id;
        $second->accessToken->created_at = now();
        $second->accessToken->save();

        $result = $this->service->listForTenant($this->tenant);

        $this->assertCount(2, $result);
        $this->assertEquals('Second', $result->first()->name);
        $this->assertEquals('First', $result->last()->name);
    }
}
