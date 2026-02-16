<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Models\Contracts\HasDefaultTenant as HasDefaultTenantContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SetFilamentDefaultTenant
{
    /**
     * Ensure a default Filament tenant is set when tenancy is enabled and no tenant is currently active.
     *
     * If tenancy is active, no tenant is set, and the database has a `tenants` table, this middleware:
     * - Uses the authenticated Filament user's `getDefaultTenant($panel)` when the user implements HasDefaultTenantContract.
     * - When running unit tests and no user-provided default exists, creates or retrieves a tenant with slug `default`.
     * When a tenant is selected, it sets the Filament tenant and, if the request has a session, stores the tenant key under `filament.tenant`.
     *
     * @param  \Illuminate\Http\Request  $request  The incoming HTTP request.
     * @param  \Closure  $next  The next middleware callback.
     * @return mixed The response from the next middleware or request handler.
     */
    public function handle(Request $request, Closure $next)
    {
        if (Filament::hasTenancy() && Filament::getTenant() === null && Schema::hasTable('tenants')) {
            $user = Filament::auth()->user();
            $tenant = null;

            // Priority 1: Match request host against tenant domain
            if ($user) {
                $host = $this->normalizeHost($request);
                if ($host) {
                    $domainTenant = \App\Models\Tenant::where('domain', $host)->first();
                    if ($domainTenant && $user->canAccessTenant($domainTenant)) {
                        $tenant = $domainTenant;
                    }
                }
            }

            // Priority 2: Query param (?tenant=slug) or X-Tenant header
            if (! $tenant && $user) {
                $slug = $request->query('tenant') ?? $request->header('X-Tenant');
                if ($slug) {
                    $slugTenant = \App\Models\Tenant::where('slug', $slug)->first();
                    if ($slugTenant && $user->canAccessTenant($slugTenant)) {
                        $tenant = $slugTenant;
                    }
                }
            }

            // Priority 3: User's configured default tenant
            if (! $tenant && $user instanceof HasDefaultTenantContract) {
                $panel = Filament::getCurrentPanel();
                $tenant = $user->getDefaultTenant($panel);
            }

            // Priority 4: Test fallback
            if (! $tenant && app()->runningUnitTests()) {
                $tenant = \App\Models\Tenant::firstOrCreate(
                    ['slug' => 'default'],
                    ['name' => 'Default Tenant']
                );
            }

            if ($tenant) {
                Filament::setTenant($tenant);
                if ($request->hasSession()) {
                    $request->session()->put('filament.tenant', $tenant->getKey());
                }
            }
        }

        return $next($request);
    }

    protected function normalizeHost(Request $request): ?string
    {
        $host = $request->getHost();
        if (! $host) {
            return null;
        }
        $host = strtolower($host);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }
}
