<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromRequest
{
    /**
     * Resolve and attach the tenant context for the incoming HTTP request.
     *
     * Resolves the tenant using the following priority: Bearer token (token->tenant_id) > request host domain > tenant with slug "default".
     * When a tenant is resolved it is attached to the request attributes as `resolved_tenant` and `resolved_tenant_by`. If a Filament user is authenticated, the middleware will attempt to set the Filament tenant. A structured debug log is emitted with resolution details.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  Closure(Request): (Response)  $next  The next middleware/action.
     * @return Response The response returned by the next middleware or action.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resolvedBy = 'default';
        $tenant = null;
        $tokenId = null;

        // Priority 1: Check Bearer Token for tenant_id
        $bearerToken = $request->bearerToken();
        if ($bearerToken) {
            $token = PersonalAccessToken::findToken($bearerToken);
            if ($token && $token->tenant_id) {
                $tenant = Tenant::find($token->tenant_id);
                if ($tenant) {
                    $resolvedBy = 'token';
                    $tokenId = $token->id;
                }
            }
        }

        // Priority 2: Match request host against tenant domain
        if (! $tenant) {
            $host = $this->normalizeHost($request);
            if ($host) {
                $tenant = Tenant::where('domain', $host)->first();
                if ($tenant) {
                    $resolvedBy = 'domain';
                }
            }
        }

        // Priority 3: Fall back to default tenant
        if (! $tenant) {
            $tenant = Tenant::where('slug', 'default')->first();
            $resolvedBy = 'default';
        }

        // Set the tenant context if found
        if ($tenant) {
            // For API context, we can't always set Filament tenant
            // because it requires an authenticated Filament user
            // Instead, we'll store it in the request for models to use
            $request->attributes->set('resolved_tenant', $tenant);
            $request->attributes->set('resolved_tenant_by', $resolvedBy);

            // Try to set Filament tenant if user is authenticated via Filament
            if (Filament::auth()->check()) {
                Filament::setTenant($tenant);
            }
        }

        // Structured logging for observability
        Log::channel('daily')->debug('Tenant resolved', [
            'resolved_by' => $resolvedBy,
            'tenant_id' => $tenant?->id,
            'tenant_slug' => $tenant?->slug,
            'token_id' => $tokenId,
            'route' => $request->path(),
            'ip' => $request->ip(),
            'host' => $request->getHost(),
        ]);

        return $next($request);
    }

    /**
     * Normalize the request host for domain-based tenant matching.
     *
     * @return string|null Normalized host (lowercase, without leading "www.") or null if the request has no host.
     */
    protected function normalizeHost(Request $request): ?string
    {
        // Prefer X-Forwarded-Host if trusted proxy is configured
        $host = $request->getHost();

        if (! $host) {
            return null;
        }

        // Lowercase
        $host = strtolower($host);

        // Strip www. prefix
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }
}
