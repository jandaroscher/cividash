<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;

trait ResolvesCurrentTenant
{
    /**
     * Resolve the current tenant from configured contexts in priority order.
     *
     * Checks, in order: Filament tenant context, ResolveTenantFromRequest middleware (public API),
     * request parameter or `X-Tenant` header (API requests), and session (web requests).
     * When a tenant is derived from request or session data, the authenticated user's access
     * to that tenant is validated; unauthorized attempts result in logging and a `null` result.
     *
     * @return Tenant|null The resolved Tenant instance, or `null` if no tenant context is available.
     */
    protected static function resolveTenant(): ?Tenant
    {
        // 1. Try Filament tenant context (for admin panel and tests)
        // Check getTenant() directly, as hasTenancy() might be false in some contexts
        try {
            $tenant = Filament::getTenant();
            if ($tenant) {
                return $tenant;
            }
        } catch (\Throwable $e) {
            // Filament might not be initialized in all contexts, continue to next method
        }

        // 2. Try tenant from ResolveTenantFromRequest middleware
        // This is set for public API requests using Token > Domain > Default priority
        // Note: Also check during unit tests since they simulate HTTP requests
        if (app()->runningInConsole() === false || app()->runningUnitTests()) {
            try {
                $request = app('request');
                if ($request && $request->attributes->has('resolved_tenant')) {
                    return $request->attributes->get('resolved_tenant');
                }
            } catch (\Throwable $e) {
                // Continue to next method
            }
        }

        // 3. Try to get tenant from HTTP request (for API requests)
        // SECURITY: Only allow tenant override if user is authenticated and has access
        if (app()->runningInConsole() === false || app()->runningUnitTests()) {
            try {
                $request = app('request');
                // Try to get user from request, fallback to auth() for compatibility
                $user = null;
                if ($request) {
                    $user = $request->user();
                }
                if (! $user) {
                    $user = auth()->user();
                }

                if ($request && $request->has('tenant')) {
                    $tenantIdentifier = $request->input('tenant');
                    $tenant = null;

                    if (is_numeric($tenantIdentifier)) {
                        $tenant = Tenant::find($tenantIdentifier);
                    } else {
                        $tenant = Tenant::where('slug', $tenantIdentifier)->first();
                    }

                    // Validate that authenticated user has access to the tenant
                    if ($tenant && $user instanceof User && $user->canAccessTenant($tenant)) {
                        return $tenant;
                    }

                    // If tenant specified but user doesn't have access, return null
                    // This will fall back to default tenant or empty result set
                    if ($tenant && $user) {
                        \Log::warning('ResolvesCurrentTenant: User attempted to access unauthorized tenant', [
                            'user_id' => $user->id,
                            'tenant_id' => $tenant->id,
                            'tenant_slug' => $tenant->slug,
                            'url' => $request->fullUrl(),
                        ]);
                    }

                    return null;
                }

                // 3. Try to get tenant from HTTP header (for API requests)
                if ($request && $request->hasHeader('X-Tenant')) {
                    $tenantIdentifier = $request->header('X-Tenant');
                    $tenant = null;

                    if (is_numeric($tenantIdentifier)) {
                        $tenant = Tenant::find($tenantIdentifier);
                    } else {
                        $tenant = Tenant::where('slug', $tenantIdentifier)->first();
                    }

                    // Validate that authenticated user has access to the tenant
                    if ($tenant && $user instanceof User && $user->canAccessTenant($tenant)) {
                        return $tenant;
                    }

                    // If tenant specified but user doesn't have access, return null
                    if ($tenant && $user) {
                        \Log::warning('ResolvesCurrentTenant: User attempted to access unauthorized tenant via header', [
                            'user_id' => $user->id,
                            'tenant_id' => $tenant->id,
                            'tenant_slug' => $tenant->slug,
                            'url' => $request->fullUrl(),
                        ]);
                    }

                    return null;
                }
            } catch (\Throwable $e) {
                // Request might not be available in all contexts, continue to next method
            }
        }

        // 4. Try to get tenant from session (for web requests)
        // SECURITY: Validate that user has access to tenant stored in session
        if (app()->runningInConsole() === false || app()->runningUnitTests()) {
            try {
                $request = app('request');
                if ($request && $request->hasSession()) {
                    $tenantId = $request->session()->get('filament.tenant');
                    if ($tenantId) {
                        $tenant = Tenant::find($tenantId);
                        // Try to get user from request, fallback to auth() for compatibility
                        $user = $request ? $request->user() : null;
                        if (! $user) {
                            $user = auth()->user();
                        }

                        // Validate that authenticated user has access to the tenant
                        // Filament should already validate this, but we check again for security
                        if ($tenant && $user instanceof User && $user->canAccessTenant($tenant)) {
                            return $tenant;
                        }

                        // If tenant in session but user doesn't have access, clear it and return null
                        if ($tenant && $user && ! $user->canAccessTenant($tenant)) {
                            \Log::warning('ResolvesCurrentTenant: User session contains unauthorized tenant, clearing', [
                                'user_id' => $user->id,
                                'tenant_id' => $tenant->id,
                                'tenant_slug' => $tenant->slug,
                            ]);
                            $request->session()->forget('filament.tenant');
                        }

                        return null;
                    }
                }
            } catch (\Throwable $e) {
                // Request might not be available in all contexts
            }
        }

        return null;
    }
}
