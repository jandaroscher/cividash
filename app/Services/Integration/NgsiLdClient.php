<?php

namespace App\Services\Integration;

use App\Contracts\Integration\ExternalDataSourceInterface;
use App\Contracts\Integration\WritableDataSourceInterface;
use App\Settings\IntegrationSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for the ETSI NGSI-LD API used by CIVITAS/CORE.
 *
 * Handles OAuth2 token acquisition via Keycloak and exposes methods
 * for querying NGSI-LD entities. This client is deliberately generic
 * so it works with any NGSI-LD-compatible platform, not only CORE.
 *
 * @see https://www.etsi.org/deliver/etsi_gs/CIM/001_099/009/01.06.01_60/gs_CIM009v010601p.pdf
 */
class NgsiLdClient implements ExternalDataSourceInterface, WritableDataSourceInterface
{
    public function __construct(
        private readonly string $apiUrl,
        private readonly string $tokenUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $scope = '',
        private readonly string $contextUrl = '',
    ) {}

    /**
     * Build an instance from database settings (tenant-aware), falling back to env/config.
     *
     * @param  array<string, mixed>  $overrides  Explicit config values that take
     *                                           precedence over stored settings.
     *                                           Recognised keys: api_url,
     *                                           oauth_token_url, oauth_client_id,
     *                                           oauth_client_secret. Used e.g. to
     *                                           test unsaved form input.
     */
    public static function fromConfig(array $overrides = []): static
    {
        $settings = rescue(fn () => app(IntegrationSettings::class), null, false);
        $config = config('integrations.civitas');

        $apiUrl = (string) (($overrides['api_url'] ?? null) ?: ($settings?->api_url ?: ($config['api_url'] ?? '')));

        return new static(
            apiUrl: rtrim($apiUrl, '/'),
            tokenUrl: (string) (($overrides['oauth_token_url'] ?? null) ?: ($settings?->oauth_token_url ?: ($config['oauth']['token_url'] ?? ''))),
            clientId: (string) (($overrides['oauth_client_id'] ?? null) ?: ($settings?->oauth_client_id ?: ($config['oauth']['client_id'] ?? ''))),
            clientSecret: (string) (($overrides['oauth_client_secret'] ?? null) ?: ($settings?->oauth_client_secret ?: ($config['oauth']['client_secret'] ?? ''))),
            scope: $config['oauth']['scope'] ?? '',
            contextUrl: (string) ($config['context_url'] ?? ''),
        );
    }

    // ---------------------------------------------------------------
    // ExternalDataSourceInterface
    // ---------------------------------------------------------------

    public function isConnected(): bool
    {
        if (empty($this->apiUrl)) {
            return false;
        }

        try {
            $response = $this->withReauth(fn () => $this->http()->get($this->apiUrl.'/types'));

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('NGSI-LD connection check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function fetchEntities(string $type, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        // NGSI-LD brokers cap the page size; never request more than 1000 at once.
        $limit = min($limit, 1000);

        $query = array_filter([
            'type' => $type,
            'limit' => $limit,
            'offset' => $offset,
            'count' => 'true',
            ...$filters,
        ]);

        $response = $this->withReauth(fn () => $this->applyContext($this->http())
            ->get($this->apiUrl.'/entities', $query));

        $response->throw();

        $total = (int) $response->header('NGSILD-Results-Count', '0');

        $json = $response->json();

        // Normalise the body shape: brokers return either a bare list of
        // entities or an envelope object with an "entities" key.
        $entities = array_is_list($json ?? []) ? $json : ($json['entities'] ?? []);

        return [
            'entities' => $entities,
            'total' => $total,
        ];
    }

    public function fetchEntity(string $id): ?array
    {
        $response = $this->withReauth(fn () => $this->applyContext($this->http())
            ->get($this->apiUrl.'/entities/'.$id));

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return $response->json();
    }

    public function getAvailableEntityTypes(): array
    {
        $response = $this->withReauth(fn () => $this->applyContext($this->http())
            ->get($this->apiUrl.'/types'));

        $response->throw();

        $json = $response->json() ?? [];

        // Stellio returns an EntityTypeList object: { "typeList": ["A", "B"] }.
        if (! array_is_list($json) && isset($json['typeList'])) {
            return collect($json['typeList'])
                ->filter()
                ->values()
                ->all();
        }

        // Other brokers return a list of objects: [{ "id": ..., "typeName": ... }].
        return collect($json)
            ->map(fn ($item) => is_array($item) ? ($item['typeName'] ?? $item['id'] ?? null) : $item)
            ->filter()
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------
    // WritableDataSourceInterface (write-back)
    // ---------------------------------------------------------------

    /**
     * Create or update an NGSI-LD entity (idempotent upsert).
     *
     * Tries POST {api_url}/entities (201/204). When the broker reports the
     * entity already exists (409 Conflict), falls back to a partial-attribute
     * update via PATCH {api_url}/entities/{id}/attrs (204). The id/type envelope
     * is stripped from the PATCH body since attrs updates carry attributes only.
     *
     * The JSON-LD @context is negotiated the same way reads do (Accept + Link),
     * with Content-Type set to application/ld+json so the broker expands the
     * request body against the configured context. Reuses the http() retry +
     * timeout policy (5xx retried, 4xx surfaced immediately).
     *
     * @param  array<string,mixed>  $entity
     *
     * @throws RequestException on any non-recoverable HTTP error.
     */
    public function upsertEntity(array $entity): void
    {
        $response = $this->withReauth(fn () => $this->writeRequest($entity)
            ->post($this->apiUrl.'/entities'));

        // Entity already exists: switch to a partial-attribute update.
        if ($response->status() === 409) {
            $id = (string) ($entity['id'] ?? '');

            if ($id === '') {
                throw new \InvalidArgumentException('Cannot PATCH attrs: entity id is empty');
            }

            $attrs = $entity;
            unset($attrs['id'], $attrs['type']);

            $patch = $this->withReauth(fn () => $this->writeRequest($attrs)
                ->patch($this->apiUrl.'/entities/'.rawurlencode($id).'/attrs'));

            $patch->throw();

            return;
        }

        $response->throw();
    }

    // ---------------------------------------------------------------
    // Request helpers
    // ---------------------------------------------------------------

    /**
     * Apply the NGSI-LD JSON-LD negotiation headers to a request.
     *
     * Always sets Accept: application/ld+json. When a JSON-LD @context URL is
     * configured, it is advertised via the Link header so the broker expands
     * terms against it.
     */
    private function applyContext(PendingRequest $request): PendingRequest
    {
        $request = $request->withHeader('Accept', 'application/ld+json');

        if ($this->contextUrl !== '') {
            $request = $request->withHeader(
                'Link',
                '<'.$this->contextUrl.'>; rel="http://www.w3.org/ns/json-ld#context"; type="application/ld+json"',
            );
        }

        return $request;
    }

    /**
     * Build a write request carrying the entity body as application/ld+json.
     *
     * The body is JSON-encoded and attached via withBody() with an explicit
     * application/ld+json content type — set EXACTLY once. (Using asJson() would
     * first set application/json and then array_merge_recursive a second
     * Content-Type, so the broker would receive "application/json,
     * application/ld+json" and reject the request with HTTP 415.)
     *
     * Negotiation otherwise mirrors reads (Accept + Link @context via
     * applyContext) and reuses the http() retry + timeout policy.
     *
     * @param  array<string,mixed>  $body
     */
    private function writeRequest(array $body): PendingRequest
    {
        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->applyContext($this->http())
            ->withBody($json, 'application/ld+json');
    }

    // ---------------------------------------------------------------
    // OAuth2 token management
    // ---------------------------------------------------------------

    /**
     * Obtain an OAuth2 access token via Client Credentials grant.
     *
     * Cached under its own TTL, derived from the token response's expires_in
     * (minus a 30s safety buffer, floor 30s) so tokens issued with a shorter
     * or longer lifetime than the historical 4-minute assumption are still
     * refreshed at the right time. Falls back to 240s when expires_in is absent.
     */
    protected function obtainAccessToken(): string
    {
        $cacheKey = $this->tokenCacheKey();

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $response = Http::connectTimeout(10)->timeout(30)->asForm()->post($this->tokenUrl, array_filter([
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => $this->scope ?: null,
        ]));

        $response->throw();

        $token = $response->json('access_token');

        if (empty($token) || ! is_string($token)) {
            throw new \RuntimeException('OAuth2 token response did not contain a valid access_token.');
        }

        $expiresIn = $response->json('expires_in');

        if (is_numeric($expiresIn) && (int) $expiresIn <= 0) {
            throw new \RuntimeException('OAuth2 token response reported a non-positive expires_in; token is already invalid.');
        }

        $ttl = is_numeric($expiresIn) ? max(30, (int) $expiresIn - 30) : 240;

        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * Cache key for the cached token, scoped by client id, token URL, AND
     * secret — so rotating the secret (without changing id/URL) can't serve
     * a token minted under the old credentials.
     */
    private function tokenCacheKey(): string
    {
        return 'civitas_oauth_token_'.md5($this->clientId.'|'.$this->tokenUrl.'|'.$this->clientSecret);
    }

    /**
     * Discard the cached token, forcing the next obtainAccessToken() call to
     * fetch a fresh one.
     */
    private function invalidateAccessToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    /**
     * Run a request builder, and on a single 401 (expired/rotated token)
     * discard the cached token and retry exactly once with a fresh one.
     * A second 401 is returned as-is to the caller.
     *
     * @param  \Closure(): Response  $makeRequest  Builds and sends the request;
     *                                             called again on retry so it
     *                                             picks up the refreshed token.
     */
    private function withReauth(\Closure $makeRequest): Response
    {
        $response = $makeRequest();

        if ($response->status() === 401 && $this->tokenUrl && $this->clientId) {
            $this->invalidateAccessToken();
            $response = $makeRequest();
        }

        return $response;
    }

    /**
     * Return an HTTP client pre-configured with the Bearer token.
     *
     * Transient failures are retried: connection errors and 5xx responses are
     * retried, while 4xx responses are surfaced immediately (a single attempt)
     * since retrying a client error is pointless.
     */
    protected function http(): PendingRequest
    {
        $request = Http::connectTimeout(10)
            ->timeout(30)
            ->retry(
                config('integrations.civitas.sync.retry_attempts', 3),
                config('integrations.civitas.sync.retry_delay_seconds', 5) * 1000,
                function (\Throwable $exception, PendingRequest $request): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    $response = $exception instanceof RequestException ? $exception->response : null;

                    // Only retry server-side (5xx) errors, never client (4xx) errors.
                    return $response instanceof Response && $response->serverError();
                },
                throw: false,
            );

        if ($this->tokenUrl && $this->clientId) {
            $request = $request->withToken($this->obtainAccessToken());
        }

        return $request;
    }
}
