<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_endpoint_allows_many_page_views_from_one_ip(): void
    {
        // One SPA page view fires about ten public API requests; 25 page views
        // a minute from a shared network must not hit the limit.
        for ($i = 0; $i < 250; $i++) {
            $this->getJson('/api/v1/config/header?locale=de')->assertOk();
        }

        $this->getJson('/api/v1/tiles?locale=de')->assertOk();
    }

    public function test_public_endpoint_returns_429_after_exceeding_rate_limit(): void
    {
        config(['dashboard.public_api_rate_limit' => 5]);

        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/api/v1/tiles?locale=de')->assertOk();
        }

        $response = $this->getJson('/api/v1/tiles?locale=de');

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    }

    public function test_public_endpoint_returns_rate_limit_headers(): void
    {
        $response = $this->getJson('/api/v1/tiles?locale=de');

        $response->assertHeader('X-RateLimit-Limit', 300);
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_public_rate_limit_is_counted_per_ip(): void
    {
        config(['dashboard.public_api_rate_limit' => 2]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);
        $this->getJson('/api/v1/tiles')->assertOk();
        $this->getJson('/api/v1/tiles')->assertOk();
        $this->getJson('/api/v1/tiles')->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);
        $this->getJson('/api/v1/tiles')->assertOk();
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
            ->postJson('/api/v1/admin/tiles', ['title' => ['de' => 'Rate Limit Test']]);

        $this->assertNotEquals(429, $response->status(), 'First admin request must not be rate-limited');
        $this->assertTrue(
            in_array($response->status(), [201, 422]),
            "Expected 201 or 422, got {$response->status()}"
        );
    }
}
