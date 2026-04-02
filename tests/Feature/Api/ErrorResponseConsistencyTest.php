<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorResponseConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['name' => 'Error Test', 'slug' => 'error-test']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id);

        $token = $user->createToken('admin', ['admin-api']);
        $token->accessToken->tenant_id = $tenant->id;
        $token->accessToken->save();
        $this->adminToken = $token->plainTextToken;
    }

    // ========== 404 Responses ==========

    public function test_404_response_is_json_with_message(): void
    {
        $response = $this->withToken($this->adminToken)
            ->deleteJson('/api/admin/tiles/999999');

        $response->assertNotFound();
        $response->assertJsonStructure(['message']);
    }

    public function test_public_404_for_nonexistent_tile_slug_is_json(): void
    {
        $response = $this->getJson('/api/tiles/does-not-exist-at-all');

        $response->assertNotFound();
        $response->assertJsonStructure(['message']);
    }

    // ========== 422 Responses ==========

    public function test_422_response_has_message_and_errors(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/tiles', []);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);
    }

    public function test_422_errors_reference_failing_field(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/tiles', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('title');
    }

    // ========== 401 Responses ==========

    public function test_401_response_is_json_with_message(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertUnauthorized();
        $response->assertJsonStructure(['message']);
    }

    // ========== 403 Responses ==========

    public function test_403_response_is_json_with_message(): void
    {
        $tenant = Tenant::create(['name' => 'Forbidden Test', 'slug' => 'forbidden-test']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id);

        $token = $user->createToken('no-admin', ['read']);
        $token->accessToken->tenant_id = $tenant->id;
        $token->accessToken->save();

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/admin/tiles', ['title' => ['de' => 'Test']]);

        $response->assertForbidden();
        $response->assertJsonStructure(['message']);
    }
}
