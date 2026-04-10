<?php

namespace App\Services\Integration;

use App\Contracts\Integration\ExternalDataSourceInterface;
use Illuminate\Http\Client\PendingRequest;
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
    ) {}

    /**
     * Build an instance from the application config.
     */
    public static function fromConfig(): static
    {
        $config = config('integrations.civitas');

        return new static(
            apiUrl: $config['api_url'] ?? '',
            tokenUrl: $config['oauth']['token_url'] ?? '',
            clientId: $config['oauth']['client_id'] ?? '',
            clientSecret: $config['oauth']['client_secret'] ?? '',
            scope: $config['oauth']['scope'] ?? '',
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
        $query = array_filter([
            'type' => $type,
            'limit' => $limit,
            'offset' => $offset,
            'count' => 'true',
            ...$filters,
        ]);

        $response = $this->http()
            ->withHeader('Accept', 'application/ld+json')
            ->get($this->apiUrl.'/entities', $query);

        $response->throw();

        $total = (int) $response->header('NGSILD-Results-Count', '0');

        return [
            'entities' => $response->json(),
            'total' => $total,
        ];
    }

    public function fetchEntity(string $id): ?array
    {
        $response = $this->http()
            ->withHeader('Accept', 'application/ld+json')
            ->get($this->apiUrl.'/entities/'.$id);

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return $response->json();
    }

    public function getAvailableEntityTypes(): array
    {
        $response = $this->http()
            ->withHeader('Accept', 'application/ld+json')
            ->get($this->apiUrl.'/types');

        $response->throw();

        return collect($response->json())
            ->pluck('id')
            ->filter()
            ->values()
            ->all();
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
     */
    protected function http(): PendingRequest
    {
        $request = Http::timeout(30)->retry(
            config('integrations.civitas.sync.retry_attempts', 3),
            config('integrations.civitas.sync.retry_delay_seconds', 5) * 1000,
        );

        if ($this->tokenUrl && $this->clientId) {
            $request = $request->withToken($this->obtainAccessToken());
        }

        return $request;
    }
}
