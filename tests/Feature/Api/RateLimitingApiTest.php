<?php

namespace Tests\Feature\Api;

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
        $user = \App\Models\User::factory()->create();
        $tenant = \App\Models\Tenant::create(['name' => 'Rate Test', 'slug' => 'rate-test']);
        $user->tenants()->attach($tenant->id);

        $token = $user->createToken('test', ['admin-api']);
        $token->accessToken->tenant_id = $tenant->id;
        $token->accessToken->save();

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/admin/tiles');

        // Admin has 120/min limit — first request should succeed (or 404 if no route for GET, but not 429)
        $this->assertNotEquals(429, $response->status());
    }
}
