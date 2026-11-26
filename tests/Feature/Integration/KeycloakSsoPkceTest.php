<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Facades\Socialite;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Keycloak requires PKCE (S256) for confidential clients on CIVITAS/CORE 2.
 * These tests drive the real Keycloak Socialite provider; only the HTTP calls
 * to Keycloak are faked.
 */
class KeycloakSsoPkceTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'integrations.keycloak_sso.enabled' => true,
            'services.keycloak.client_id' => 'cividash',
            'services.keycloak.client_secret' => 'test-secret',
            'services.keycloak.redirect' => 'http://localhost/admin/auth/keycloak/callback',
            'services.keycloak.base_url' => 'https://keycloak.example.org',
            'services.keycloak.base_url_internal' => null,
            'services.keycloak.realms' => 'civitas',
        ]);
    }

    public function test_redirect_sends_an_s256_code_challenge(): void
    {
        $response = $this->get(route('auth.keycloak.redirect'));

        $response->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('S256', $query['code_challenge_method'] ?? null);
        $this->assertNotEmpty($query['code_challenge'] ?? null);
    }

    public function test_callback_sends_the_code_verifier_that_matches_the_challenge(): void
    {
        $redirect = $this->get(route('auth.keycloak.redirect'));
        parse_str((string) parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

        $requests = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 300])),
            new Response(200, [], json_encode([
                'sub' => 'kc-pkce',
                'email' => 'pkce@example.com',
                'email_verified' => true,
                'given_name' => 'Pkce',
                'family_name' => 'User',
                'preferred_username' => 'pkce',
            ])),
        ]));
        $stack->push(Middleware::history($requests));
        // The provider keeps the request it was built with, so build it fresh
        // inside the callback request and hand it the faked Keycloak client.
        // It is built before callback() applies base_url_internal, which this
        // test leaves unset.
        Socialite::forgetDrivers();
        Event::listen(RouteMatched::class, fn () => Socialite::driver('keycloak')
            ->setHttpClient(new Client(['handler' => $stack])));

        $response = $this->get(route('auth.keycloak.callback', ['code' => 'auth-code', 'state' => $query['state']]));

        $response->assertRedirect('/admin');
        $this->assertAuthenticated();

        parse_str((string) $requests[0]['request']->getBody(), $tokenRequest);
        $verifier = $tokenRequest['code_verifier'] ?? '';

        $this->assertNotSame('', $verifier, 'token request carries no code_verifier');
        $this->assertSame(
            $query['code_challenge'],
            rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        );
    }
}
