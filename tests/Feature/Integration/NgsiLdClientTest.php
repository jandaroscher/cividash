<?php

namespace Tests\Feature\Integration;

use App\Contracts\Integration\WritableDataSourceInterface;
use App\Services\Integration\NgsiLdClient;
use App\Settings\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
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
            'broker.example.com/*' => fn () => throw new ConnectionException('connection refused'),
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

    // -----------------------------------------------------------------
    // Write-back: upsertEntity
    // -----------------------------------------------------------------

    public function test_implements_writable_data_source_interface(): void
    {
        $this->assertInstanceOf(
            WritableDataSourceInterface::class,
            $this->client(),
        );
    }

    public function test_upsert_entity_posts_to_entities_with_bearer_and_context(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities' => Http::response('', 201),
        ]);

        $contextUrl = 'https://broker.example.com/jsonldContexts/default';
        $entity = [
            'id' => 'urn:ngsi-ld:NachhaltigkeitsIndikator:co2',
            'type' => 'NachhaltigkeitsIndikator',
            'dataPoints' => ['type' => 'Property', 'value' => [['period' => '2023', 'value' => 1.5]]],
        ];

        $this->client($contextUrl)->upsertEntity($entity);

        Http::assertSent(function (Request $request) use ($contextUrl, $entity) {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/entities')) {
                return false;
            }

            $bearer = collect($request->header('Authorization'))->contains('Bearer tok-123');
            $contentType = collect($request->header('Content-Type'))->contains(fn ($v) => str_contains($v, 'application/ld+json'));
            $link = collect($request->header('Link'))->contains(fn ($v) => str_contains($v, $contextUrl));

            return $bearer
                && $contentType
                && $link
                && $request['id'] === $entity['id']
                && $request['dataPoints'] === $entity['dataPoints'];
        });
    }

    public function test_upsert_entity_falls_back_to_patch_attrs_on_409_conflict(): void
    {
        $entityId = 'urn:ngsi-ld:NachhaltigkeitsIndikator:co2';

        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities/'.urlencode($entityId).'/attrs' => Http::response('', 204),
            'broker.example.com/context/ngsi-ld/entities' => Http::response(['detail' => 'already exists'], 409),
        ]);

        $entity = [
            'id' => $entityId,
            'type' => 'NachhaltigkeitsIndikator',
            'name' => ['type' => 'LanguageProperty', 'languageMap' => ['de' => 'X']],
        ];

        $this->client()->upsertEntity($entity);

        // First a POST /entities (409), then a PATCH /entities/{id}/attrs (204).
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/entities'));

        Http::assertSent(function (Request $request) use ($entityId) {
            if ($request->method() !== 'PATCH') {
                return false;
            }

            // The PATCH body carries attributes, not the id/type envelope.
            return str_contains($request->url(), '/entities/'.urlencode($entityId).'/attrs')
                && ! isset($request['id'])
                && ! isset($request['type'])
                && isset($request['name']);
        });
    }

    public function test_upsert_entity_treats_204_create_as_success_without_patch(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities' => Http::response('', 204),
        ]);

        $this->client()->upsertEntity([
            'id' => 'urn:ngsi-ld:NachhaltigkeitsIndikator:co2',
            'type' => 'NachhaltigkeitsIndikator',
        ]);

        $patchCount = 0;
        Http::recorded(function (Request $request) use (&$patchCount) {
            if ($request->method() === 'PATCH') {
                $patchCount++;
            }

            return false;
        });

        $this->assertSame(0, $patchCount, 'A 204 create must NOT trigger a PATCH fallback.');
    }

    public function test_upsert_entity_surfaces_non_conflict_4xx(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities' => Http::response(['detail' => 'bad request'], 400),
        ]);

        $this->expectException(RequestException::class);

        $this->client()->upsertEntity([
            'id' => 'urn:ngsi-ld:NachhaltigkeitsIndikator:co2',
            'type' => 'NachhaltigkeitsIndikator',
        ]);
    }

    public function test_upsert_entity_sends_exactly_one_ld_json_content_type_header(): void
    {
        // Regression guard: the write body must carry a SINGLE Content-Type of
        // application/ld+json. A duplicate (application/json, application/ld+json)
        // makes Stellio reject the request with HTTP 415 in production.
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities' => Http::response('', 201),
        ]);

        $this->client()->upsertEntity([
            'id' => 'urn:ngsi-ld:NachhaltigkeitsIndikator:co2',
            'type' => 'NachhaltigkeitsIndikator',
        ]);

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/entities')) {
                return false;
            }

            $contentType = $request->header('Content-Type');

            return is_array($contentType)
                && count($contentType) === 1
                && $contentType[0] === 'application/ld+json';
        });
    }

    public function test_upsert_entity_throws_invalid_argument_on_409_with_empty_id(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities' => Http::response(['detail' => 'exists'], 409),
        ]);

        $this->expectException(\InvalidArgumentException::class);

        // No usable id => cannot build the PATCH /attrs URL on the 409 fallback.
        $this->client()->upsertEntity(['type' => 'NachhaltigkeitsIndikator', 'id' => '']);
    }

    // -----------------------------------------------------------------
    // OAuth token robustness
    // -----------------------------------------------------------------

    public function test_token_ttl_is_derived_from_expires_in_with_30s_buffer(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123', 'expires_in' => 100]),
            'broker.example.com/*' => Http::response([], 200, ['NGSILD-Results-Count' => '0']),
        ]);

        $client = $this->client();
        $client->fetchEntities('AirQualityObserved');

        // TTL = 100 - 30 = 70s: still cached just before, expired just after.
        $this->travel(69)->seconds();
        $client->fetchEntities('AirQualityObserved');
        Http::assertSentCount(3); // 1 token + 2 entity calls, still cached

        $this->travel(2)->seconds(); // total 71s since caching
        $client->fetchEntities('AirQualityObserved');
        Http::assertSentCount(5); // new token fetched + 3rd entity call
    }

    public function test_token_ttl_falls_back_to_240s_when_expires_in_missing(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/*' => Http::response([], 200, ['NGSILD-Results-Count' => '0']),
        ]);

        $client = $this->client();
        $client->fetchEntities('AirQualityObserved');

        $this->travel(239)->seconds();
        $client->fetchEntities('AirQualityObserved');
        Http::assertSentCount(3);

        $this->travel(2)->seconds(); // total 241s
        $client->fetchEntities('AirQualityObserved');
        Http::assertSentCount(5);
    }

    public function test_reauthenticates_once_on_401_and_returns_retry_result(): void
    {
        $tokenCalls = 0;
        $entityCalls = 0;

        Http::fake([
            'keycloak.example.com/token' => function () use (&$tokenCalls) {
                $tokenCalls++;

                return Http::response(['access_token' => 'tok-'.$tokenCalls, 'expires_in' => 300]);
            },
            'broker.example.com/*' => function () use (&$entityCalls) {
                $entityCalls++;

                // First broker call: expired/rotated token => 401. Second: success.
                if ($entityCalls === 1) {
                    return Http::response(['detail' => 'unauthorized'], 401);
                }

                return Http::response([], 200, ['NGSILD-Results-Count' => '7']);
            },
        ]);

        $result = $this->client()->fetchEntities('AirQualityObserved');

        $this->assertSame(2, $tokenCalls, 'A 401 must trigger exactly one re-authentication.');
        $this->assertSame(2, $entityCalls, 'The request must be retried exactly once after re-auth.');
        $this->assertSame(7, $result['total']);
    }

    public function test_second_consecutive_401_is_not_retried_again(): void
    {
        $tokenCalls = 0;
        $entityCalls = 0;

        Http::fake([
            'keycloak.example.com/token' => function () use (&$tokenCalls) {
                $tokenCalls++;

                return Http::response(['access_token' => 'tok-'.$tokenCalls, 'expires_in' => 300]);
            },
            'broker.example.com/*' => function () use (&$entityCalls) {
                $entityCalls++;

                return Http::response(['detail' => 'unauthorized'], 401);
            },
        ]);

        $this->expectException(RequestException::class);

        try {
            $this->client()->fetchEntities('AirQualityObserved');
        } finally {
            $this->assertSame(2, $tokenCalls, 'Only one re-authentication attempt is allowed.');
            $this->assertSame(2, $entityCalls, 'Only one retry is allowed after the first 401.');
        }
    }

    public function test_token_ttl_handles_string_expires_in(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123', 'expires_in' => '100']),
            'broker.example.com/*' => Http::response([], 200, ['NGSILD-Results-Count' => '0']),
        ]);

        $client = $this->client();
        $client->fetchEntities('AirQualityObserved');

        // TTL = 100 - 30 = 70s.
        $this->travel(69)->seconds();
        $client->fetchEntities('AirQualityObserved');
        Http::assertSentCount(3); // still cached

        $this->travel(2)->seconds(); // total 71s
        $client->fetchEntities('AirQualityObserved');
        Http::assertSentCount(5); // re-fetched
    }

    public function test_non_positive_expires_in_throws_instead_of_caching(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123', 'expires_in' => 0]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('non-positive expires_in');

        $this->client()->fetchEntities('AirQualityObserved');
    }

    public function test_reauthenticates_on_401_during_patch_fallback(): void
    {
        $entityId = 'urn:ngsi-ld:NachhaltigkeitsIndikator:co2';
        $tokenCalls = 0;
        $patchCalls = 0;

        Http::fake([
            'keycloak.example.com/token' => function () use (&$tokenCalls) {
                $tokenCalls++;

                return Http::response(['access_token' => 'tok-'.$tokenCalls, 'expires_in' => 300]);
            },
            'broker.example.com/context/ngsi-ld/entities/'.urlencode($entityId).'/attrs' => function () use (&$patchCalls) {
                $patchCalls++;

                // First PATCH: expired/rotated token => 401. Second: success.
                return $patchCalls === 1
                    ? Http::response(['detail' => 'unauthorized'], 401)
                    : Http::response('', 204);
            },
            'broker.example.com/context/ngsi-ld/entities' => Http::response(['detail' => 'already exists'], 409),
        ]);

        $this->client()->upsertEntity([
            'id' => $entityId,
            'type' => 'NachhaltigkeitsIndikator',
            'name' => ['type' => 'LanguageProperty', 'languageMap' => ['de' => 'X']],
        ]);

        $this->assertSame(2, $tokenCalls, 'A 401 on the PATCH fallback must trigger exactly one re-authentication.');
        $this->assertSame(2, $patchCalls, 'The PATCH must be retried exactly once after re-auth.');
    }

    public function test_token_endpoint_500_during_reauth_throws_without_further_broker_calls(): void
    {
        $tokenCalls = 0;
        $entityCalls = 0;

        Http::fake([
            'keycloak.example.com/token' => function () use (&$tokenCalls) {
                $tokenCalls++;

                if ($tokenCalls === 1) {
                    return Http::response(['access_token' => 'tok-1', 'expires_in' => 300]);
                }

                return Http::response(['detail' => 'server error'], 500);
            },
            'broker.example.com/*' => function () use (&$entityCalls) {
                $entityCalls++;

                return Http::response(['detail' => 'unauthorized'], 401);
            },
        ]);

        $this->expectException(RequestException::class);

        try {
            $this->client()->fetchEntities('AirQualityObserved');
        } finally {
            // 1 initial token + 1 failed re-auth attempt (token endpoint has no retry policy).
            $this->assertSame(2, $tokenCalls);
            $this->assertSame(1, $entityCalls, 'No further broker call once re-authentication itself fails.');
        }
    }
}
