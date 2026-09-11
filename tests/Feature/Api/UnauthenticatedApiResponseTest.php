<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnauthenticatedApiResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_api_route_returns_401_json_without_accept_header(): void
    {
        // Browser-style request without Accept: application/json. Laravel would
        // otherwise try to redirect to route('login'), which the SPA does not define.
        $response = $this->call('GET', '/api/tenants/default/users', [], [], [], ['HTTP_ACCEPT' => 'text/html']);

        $response->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
    }
}
