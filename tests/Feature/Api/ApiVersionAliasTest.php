<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiVersionAliasTest extends TestCase
{
    use RefreshDatabase;

    public function test_unversioned_alias_matches_v1_response_and_carries_deprecation_headers(): void
    {
        $aliasResponse = $this->getJson('/api/tiles');
        $v1Response = $this->getJson('/api/v1/tiles');

        $aliasResponse->assertStatus($v1Response->getStatusCode());
        $this->assertSame($v1Response->json(), $aliasResponse->json());

        $aliasResponse->assertHeader('Deprecation', 'true');
        $aliasResponse->assertHeaderMissing('Sunset');

        $link = $aliasResponse->headers->get('Link');
        $this->assertNotNull($link);
        $this->assertStringContainsString('/api/v1/tiles', $link);
        $this->assertStringContainsString('rel="successor-version"', $link);
    }

    public function test_v1_response_does_not_carry_deprecation_headers(): void
    {
        $response = $this->getJson('/api/v1/tiles');

        $response->assertHeaderMissing('Deprecation');
        $response->assertHeaderMissing('Link');
    }
}
