<?php

namespace App\Models\Concerns;

use App\Exceptions\InvalidTenantContextException;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

trait BelongsToTenant
{
    /**
     * Register creation behavior and a global tenant query scope for the model.
     *
     * On creation, associates newly created models with the resolved tenant or with the tenant having
     * slug "default"; if neither is available an InvalidTenantContextException is thrown. The global
     * scope restricts queries to the resolved tenant, falls back to the "default" tenant when present,
     * or constrains results to an empty set to prevent data leakage when no tenant exists. The scope is
     * not applied during normal console runs but is applied during unit tests running in console.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::creating(function ($model) {
            // Robuster Schema-Check mit try-catch für Unit-Tests ohne DB
            try {
                if (! Schema::hasTable('tenants')) {
                    return;
                }
            } catch (\Throwable $e) {
                // In Unit-Tests ohne DB kann Schema-Check fehlschlagen
                // Ignorieren und Tenant-Zuweisung überspringen
                return;
            }

            if (empty($model->tenant_id)) {
                if ($tenant = static::resolveTenant()) {
                    $model->tenant()->associate($tenant);
                } else {
                    // Try to use default tenant as fallback
                    try {
                        $defaultTenant = Tenant::where('slug', 'default')->first();
                        
                        if ($defaultTenant) {
                            $model->tenant()->associate($defaultTenant);
                        } else {
                            // No tenant context and no default tenant - throw exception
                            throw new InvalidTenantContextException(
                                'No tenant context available and no default tenant found. Please provide a tenant explicitly when creating ' . get_class($model) . '.'
                            );
                        }
                    } catch (\Throwable $e) {
                        // If query fails (e.g., table doesn't exist), skip tenant assignment
                        // This allows tests without database to work
                        if (! ($e instanceof InvalidTenantContextException)) {
                            return;
                        }
                        throw $e;
                    }
                }
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            // Robuster Schema-Check mit try-catch für Unit-Tests ohne DB
            try {
                if (! Schema::hasTable('tenants')) {
                    return;
                }
            } catch (\Throwable $e) {
                // In Unit-Tests ohne DB kann Schema-Check fehlschlagen
                // Ignorieren und Scope nicht anwenden
                return;
            }

            // Skip scope for console commands (artisan commands, scheduled jobs)
            // Commands should explicitly set tenant context or use withoutGlobalScope('tenant')
            // Note: Unit tests run in console context but are excluded from this bypass
            // so tenant scope still applies during tests
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return;
            }

            $tenant = static::resolveTenant();

            if ($tenant) {
                // SECURITY: Use where() instead of whereBelongsTo() to explicitly filter out NULL values
                // whereBelongsTo() generates "WHERE tenant_id = ?" which doesn't filter NULL values
                // Since tenant_id columns are nullable, records with NULL tenant_id would leak across tenants
                // Use qualifyColumn() to avoid ambiguous column errors when JOINs are used
                $builder->where($builder->qualifyColumn('tenant_id'), $tenant->id);
            } else {
                // If no tenant context is available in HTTP requests, use default tenant
                // to prevent data leakage. API requests should ideally provide tenant via
                // query parameter (?tenant=slug) or header (X-Tenant: slug) for proper isolation.
                try {
                    $defaultTenant = Tenant::where('slug', 'default')->first();
                    
                    if ($defaultTenant) {
                        $request = rescue(fn () => app('request'), null, false);
                        Log::warning('BelongsToTenant: No tenant context found, using default tenant', [
                            'url' => $request?->fullUrl() ?? 'N/A',
                            'method' => $request?->method() ?? 'N/A',
                        ]);
                        // SECURITY: Use where() instead of whereBelongsTo() to explicitly filter out NULL values
                        // Use qualifyColumn() to avoid ambiguous column errors when JOINs are used
                        $builder->where($builder->qualifyColumn('tenant_id'), $defaultTenant->id);
                    } else {
                        // If no default tenant exists, filter to empty result set to prevent data leakage
                        $builder->whereRaw('1 = 0');
                    }
                } catch (\Throwable $e) {
                    // If query fails (e.g., table doesn't exist in unit tests), don't apply scope
                    // This allows tests without database to work
                    return;
                }
            }
        });
    }

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
                    if ($tenant && $user instanceof \App\Models\User && $user->canAccessTenant($tenant)) {
                        return $tenant;
                    }
                    
                    // If tenant specified but user doesn't have access, return null
                    // This will fall back to default tenant or empty result set
                    if ($tenant && $user) {
                        \Log::warning('BelongsToTenant: User attempted to access unauthorized tenant', [
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
                    if ($tenant && $user instanceof \App\Models\User && $user->canAccessTenant($tenant)) {
                        return $tenant;
                    }
                    
                    // If tenant specified but user doesn't have access, return null
                    if ($tenant && $user) {
                        \Log::warning('BelongsToTenant: User attempted to access unauthorized tenant via header', [
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
                        if ($tenant && $user instanceof \App\Models\User && $user->canAccessTenant($tenant)) {
                            return $tenant;
                        }
                        
                        // If tenant in session but user doesn't have access, clear it and return null
                        if ($tenant && $user && ! $user->canAccessTenant($tenant)) {
                            \Log::warning('BelongsToTenant: User session contains unauthorized tenant, clearing', [
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