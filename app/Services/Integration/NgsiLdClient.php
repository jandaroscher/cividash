<?php

namespace App\Services\Integration;

use App\Contracts\Integration\ExternalDataSourceInterface;
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
class NgsiLdClient implements ExternalDataSourceInterface
{
    private ?string $accessToken = null;

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
     */
    public static function fromConfig(): static
    {
        $settings = rescue(fn () => app(\App\Settings\IntegrationSettings::class), null, false);
        $config = config('integrations.civitas');

        $apiUrl = $settings?->api_url ?: ($config['api_url'] ?? '');

        return new static(
            apiUrl: rtrim($apiUrl, '/'),
            tokenUrl: $settings?->oauth_token_url ?: ($config['oauth']['token_url'] ?? ''),
            clientId: $settings?->oauth_client_id ?: ($config['oauth']['client_id'] ?? ''),
            clientSecret: $settings?->oauth_client_secret ?: ($config['oauth']['client_secret'] ?? ''),
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
            $response = $this->http()->get($this->apiUrl.'/types');

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

        $response = $this->applyContext($this->http())
            ->get($this->apiUrl.'/entities', $query);

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
        $response = $this->applyContext($this->http())
            ->get($this->apiUrl.'/entities/'.$id);

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return $response->json();
    }

    public function getAvailableEntityTypes(): array
    {
        $response = $this->applyContext($this->http())
            ->get($this->apiUrl.'/types');

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

    // ---------------------------------------------------------------
    // OAuth2 token management
    // ---------------------------------------------------------------

    /**
     * Obtain an OAuth2 access token via Client Credentials grant.
     *
     * Tokens are cached for 4 minutes (Keycloak default lifetime is 5 min).
     */
    protected function obtainAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $cacheKey = 'civitas_oauth_token_'.md5($this->clientId.$this->tokenUrl);

        $this->accessToken = Cache::remember($cacheKey, 240, function () {
            $response = Http::asForm()->post($this->tokenUrl, array_filter([
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

            return $token;
        });

        return $this->accessToken;
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
