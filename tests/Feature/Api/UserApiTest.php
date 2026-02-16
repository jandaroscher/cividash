<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::where('slug', 'default')->first();
    }

    public function test_authenticated_user_returns_user_data(): void
    {
        // User::booted() auto-attaches the default tenant, no need to attach again
        $user = User::factory()->create();
        $token = $user->createToken('test', ['*']);
        $token->accessToken->update(['tenant_id' => $this->tenant->id]);

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('name', $user->name)
            ->assertJsonPath('email', $user->email);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized();
    }
}
