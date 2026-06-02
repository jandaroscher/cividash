<?php

namespace Tests\Feature\Integration;

use App\Services\Integration\NgsiLdClient;
use App\Settings\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NgsiLdClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        config([
            'integrations.civitas.api_url' => 'https://broker.example.com/context/ngsi-ld',
            'integrations.civitas.context_url' => null,
            'integrations.civitas.oauth.token_url' => 'https://keycloak.example.com/token',
            'integrations.civitas.oauth.client_id' => 'dashboard',
            'integrations.civitas.oauth.client_secret' => 'secret',
            'integrations.civitas.oauth.scope' => '',
            'integrations.civitas.sync.retry_attempts' => 3,
            'integrations.civitas.sync.retry_delay_seconds' => 0,
        ]);
    }

    private function client(string $contextUrl = ''): NgsiLdClient
    {
        return new NgsiLdClient(
            apiUrl: 'https://broker.example.com/context/ngsi-ld',
            tokenUrl: 'https://keycloak.example.com/token',
            clientId: 'dashboard',
            clientSecret: 'secret',
            scope: '',
            contextUrl: $contextUrl,
        );
    }

    public function test_obtains_oauth_token_and_caches_it(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123', 'expires_in' => 300]),
            'broker.example.com/*' => Http::response([], 200, ['NGSILD-Results-Count' => '0']),
        ]);

        $client = $this->client();

        // Two consecutive entity fetches; the token must only be requested once.
        $client->fetchEntities('AirQualityObserved');
        $client->fetchEntities('AirQualityObserved');

        Http::assertSentCount(3); // 1 token + 2 entity calls

        $tokenCalls = 0;
        Http::recorded(function (Request $request) use (&$tokenCalls) {
            if (str_contains($request->url(), 'keycloak.example.com/token')) {
                $tokenCalls++;
            }

            return false;
        });

        $this->assertSame(1, $tokenCalls, 'OAuth token endpoint should only be hit once due to caching.');
    }

    public function test_fetch_entities_reads_results_count_header_into_total(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/*' => Http::response(
                [['id' => 'urn:a'], ['id' => 'urn:b']],
                200,
                ['NGSILD-Results-Count' => '42'],
            ),
        ]);

        $result = $this->client()->fetchEntities('AirQualityObserved');

        $this->assertSame(42, $result['total']);
        $this->assertCount(2, $result['entities']);
    }

    public function test_fetch_entities_sends_jsonld_accept_and_link_context_header(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/*' => Http::response([], 200, ['NGSILD-Results-Count' => '0']),
        ]);

        $contextUrl = 'https://broker.example.com/jsonldContexts/default';

        $this->client($contextUrl)->fetchEntities('AirQualityObserved');

        Http::assertSent(function (Request $request) use ($contextUrl) {
            if (! str_contains($request->url(), '/entities')) {
                return false;
            }

            $accept = $request->header('Accept');
            $link = $request->header('Link');

            return in_array('application/ld+json', $accept, true)
                && collect($link)->contains(fn ($value) => str_contains($value, $contextUrl)
                    && str_contains($value, 'rel="http://www.w3.org/ns/json-ld#context"'));
        });
    }

    public function test_fetch_entity_returns_null_on_404(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/*' => Http::response(['detail' => 'not found'], 404),
        ]);

        $this->assertNull($this->client()->fetchEntity('urn:ngsi-ld:Missing:1'));
    }

    public function test_get_available_entity_types_parses_type_list_form(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/*' => Http::response([
                'id' => 'urn:ngsi-ld:EntityTypeList:abc',
                'type' => 'EntityTypeList',
                'typeList' => ['AirQualityObserved', 'WeatherObserved', 'Device'],
            ]),
        ]);

        $types = $this->client()->getAvailableEntityTypes();

        $this->assertSame(['AirQualityObserved', 'WeatherObserved', 'Device'], $types);
    }

    public function test_get_available_entity_types_parses_object_list_form(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/*' => Http::response([
                ['id' => 'https://uri.fiware.org/ns/data-models#AirQualityObserved', 'typeName' => 'AirQualityObserved'],
                ['id' => 'https://uri.fiware.org/ns/data-models#WeatherObserved', 'typeName' => 'WeatherObserved'],
            ]),
        ]);

        $types = $this->client()->getAvailableEntityTypes();

        $this->assertSame(['AirQualityObserved', 'WeatherObserved'], $types);
    }

    public function test_is_connected_returns_false_on_connection_error(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('connection refused'),
        ]);

        $this->assertFalse($this->client()->isConnected());
    }

    public function test_http_does_not_retry_on_4xx(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/*' => Http::response(['detail' => 'bad request'], 400),
        ]);

        try {
            $this->client()->getAvailableEntityTypes();
        } catch (\Throwable) {
            // expected to throw on 4xx
        }

        $count = 0;
        Http::recorded(function (Request $request) use (&$count) {
            if (str_contains($request->url(), '/types')) {
                $count++;
            }

            return false;
        });

        $this->assertSame(1, $count, '4xx responses must NOT be retried.');
    }

    public function test_http_retries_on_5xx(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/*' => Http::response(['detail' => 'server error'], 500),
        ]);

        try {
            $this->client()->getAvailableEntityTypes();
        } catch (\Throwable) {
            // expected to throw after exhausting retries
        }

        $count = 0;
        Http::recorded(function (Request $request) use (&$count) {
            if (str_contains($request->url(), '/types')) {
                $count++;
            }

            return false;
        });

        $this->assertGreaterThan(1, $count, '5xx responses must be retried.');
    }

    public function test_from_config_prefers_integration_settings_over_config(): void
    {
        $settings = app(IntegrationSettings::class);
        $settings->api_url = 'https://settings-broker.example.com/context/ngsi-ld/';
        $settings->oauth_token_url = 'https://settings-keycloak.example.com/token';
        $settings->oauth_client_id = 'settings-client';
        $settings->oauth_client_secret = 'settings-secret';
        $settings->save();

        config([
            'integrations.civitas.api_url' => 'https://config-broker.example.com/context/ngsi-ld',
            'integrations.civitas.context_url' => 'https://config-broker.example.com/ctx',
            'integrations.civitas.oauth.token_url' => 'https://config-keycloak.example.com/token',
            'integrations.civitas.oauth.client_id' => 'config-client',
            'integrations.civitas.oauth.client_secret' => 'config-secret',
        ]);

        Http::fake([
            'settings-keycloak.example.com/token' => Http::response(['access_token' => 'tok-settings']),
            'settings-broker.example.com/*' => Http::response([], 200, ['NGSILD-Results-Count' => '0']),
        ]);

        $client = NgsiLdClient::fromConfig();
        $client->fetchEntities('AirQualityObserved');

        // Settings api_url is used (with trailing slash trimmed), and the
        // settings token URL is hit rather than the config one.
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'settings-broker.example.com/context/ngsi-ld/entities')
            && ! str_contains($request->url(), '//entities'));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'settings-keycloak.example.com/token'));
    }
}
