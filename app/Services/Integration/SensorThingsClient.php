<?php

namespace App\Services\Integration;

use App\Contracts\Integration\ExternalDataSourceInterface;
use App\Settings\IntegrationSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for the OGC SensorThings API used by FROST (part of CIVITAS/CORE).
 *
 * Maps SensorThings concepts to the ExternalDataSourceInterface:
 *   - "entity type" → SensorThings resource (Things, Datastreams, Observations)
 *   - "entity"      → a SensorThings Thing with expanded Datastreams & Observations
 *
 * @see https://docs.ogc.org/is/18-088/18-088.html
 */
class SensorThingsClient implements ExternalDataSourceInterface
{
    private int $tokenCacheTtl = 240;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $tokenUrl = '',
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
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

        return new static(
            baseUrl: (string) (($overrides['api_url'] ?? null) ?: ($settings?->api_url ?: ($config['api_url'] ?? ''))),
            tokenUrl: (string) (($overrides['oauth_token_url'] ?? null) ?: ($settings?->oauth_token_url ?: ($config['oauth']['token_url'] ?? ''))),
            clientId: (string) (($overrides['oauth_client_id'] ?? null) ?: ($settings?->oauth_client_id ?: ($config['oauth']['client_id'] ?? ''))),
            clientSecret: (string) (($overrides['oauth_client_secret'] ?? null) ?: ($settings?->oauth_client_secret ?: ($config['oauth']['client_secret'] ?? ''))),
        );
    }

    // ---------------------------------------------------------------
    // ExternalDataSourceInterface
    // ---------------------------------------------------------------

    public function isConnected(): bool
    {
        if (empty($this->baseUrl)) {
            return false;
        }

        try {
            $response = $this->http()->get($this->baseUrl);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('SensorThings connection check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function fetchEntities(string $type, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $url = $this->baseUrl.'/'.$type;

        $query = [
            '$top' => $limit,
            '$skip' => $offset,
            '$count' => 'true',
        ];

        // Support $expand, $filter, $orderby etc. via filters array
        foreach ($filters as $key => $value) {
            $query[$key] = $value;
        }

        $response = $this->http()->get($url, $query);
        $response->throw();

        $data = $response->json();

        return [
            'entities' => $data['value'] ?? [],
            'total' => (int) ($data['@iot.count'] ?? count($data['value'] ?? [])),
        ];
    }

    public function fetchEntity(string $id): ?array
    {
        // For SensorThings, id format is "Things(2)" or just "2"
        $path = str_contains($id, '(') ? $id : "Things({$id})";

        $response = $this->http()->get($this->baseUrl.'/'.$path, [
            '$expand' => 'Datastreams($expand=Observations($orderby=phenomenonTime asc),ObservedProperty),Locations',
        ]);

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return $response->json();
    }

    public function getAvailableEntityTypes(): array
    {
        $response = $this->http()->get($this->baseUrl);
        $response->throw();

        return collect($response->json('value', []))
            ->pluck('name')
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------
    // SensorThings-specific queries
    // ---------------------------------------------------------------

    /**
     * Fetch all Things with their Datastreams, Observations, and Locations expanded.
     */
    public function fetchThingsWithData(int $limit = 100, int $offset = 0): array
    {
        return $this->fetchEntities('Things', [
            '$expand' => 'Datastreams($expand=Observations($orderby=phenomenonTime asc),ObservedProperty),Locations',
        ], $limit, $offset);
    }

    /**
     * Fetch a single Thing by numeric ID with all related data.
     */
    public function fetchThing(int $id): ?array
    {
        return $this->fetchEntity("Things({$id})");
    }

    // ---------------------------------------------------------------
    // OAuth2 token management
    // ---------------------------------------------------------------

    private function getTokenCacheTtl(): int
    {
        return $this->tokenCacheTtl;
    }

    protected function obtainAccessToken(): ?string
    {
        if (empty($this->tokenUrl) || empty($this->clientId)) {
            return null;
        }

        $cacheKey = 'sensorthings_oauth_token_'.md5($this->clientId.$this->tokenUrl);

        return Cache::remember($cacheKey, $this->getTokenCacheTtl(), function () {
            $params = [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ];

            $scope = config('integrations.civitas.oauth.scope', '');
            if (! empty($scope)) {
                $params['scope'] = $scope;
            }

            $response = Http::asForm()->post($this->tokenUrl, $params);
            $response->throw();

            // Update cache TTL based on token response for subsequent calls
            $expiresIn = $response->json('expires_in');
            if ($expiresIn && $expiresIn > 0) {
                $this->tokenCacheTtl = max(1, (int) $expiresIn - 30);
            }

            return $response->json('access_token');
        });
    }

    protected function http(): PendingRequest
    {
        $request = Http::timeout(30)->retry(
            config('integrations.civitas.sync.retry_attempts', 3),
            config('integrations.civitas.sync.retry_delay_seconds', 5) * 1000,
        );

        $token = $this->obtainAccessToken();
        if ($token) {
            $request = $request->withToken($token);
        }

        return $request;
    }
}
