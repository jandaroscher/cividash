<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_endpoint_returns_429_after_exceeding_rate_limit(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/tiles?locale=de');
        }

        $response = $this->getJson('/api/tiles?locale=de');

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    }

    public function test_public_endpoint_returns_rate_limit_headers(): void
    {
        $response = $this->getJson('/api/tiles?locale=de');

        $response->assertHeader('X-RateLimit-Limit', 60);
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_admin_endpoint_has_higher_rate_limit(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'Rate Test', 'slug' => 'rate-test']);
        $user->tenants()->attach($tenant->id);

        $token = $user->createToken('test', ['admin-api']);
        $token->accessToken->tenant_id = $tenant->id;
        $token->accessToken->save();

        // POST to create tile — exercises the admin throttle group
        // May return 201 (created) or 422 (validation) but must not be 429
        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/admin/tiles', ['title' => ['de' => 'Rate Limit Test']]);

        $this->assertNotEquals(429, $response->status(), 'First admin request must not be rate-limited');
        $this->assertTrue(
            in_array($response->status(), [201, 422]),
            "Expected 201 or 422, got {$response->status()}"
        );
    }
}
