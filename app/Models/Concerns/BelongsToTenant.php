<?php

namespace App\Models\Concerns;

use App\Exceptions\InvalidTenantContextException;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Request;

trait BelongsToTenant
{
    /**
     * Boots the BelongsToTenant trait for the model by registering creation behavior and a global tenant query scope.
     *
     * On model creation, associates the new model with a resolved tenant or a fallback tenant when no tenant_id is set.
     * Adds a global scope that restricts queries to the current tenant; when no tenant context is available the scope
     * will apply a default tenant if present or constrain results to an empty set. The global scope is skipped in
     * console contexts (except when running unit tests).
     */
    protected static function bootBelongsToTenant(): void
    {
        static::creating(function ($model) {
            if (! Schema::hasTable('tenants')) {
                return;
            }

            if (empty($model->tenant_id)) {
                if ($tenant = static::resolveTenant()) {
                    $model->tenant()->associate($tenant);
                } else {
                    // Try to use default tenant as fallback
                    $defaultTenant = Tenant::where('slug', 'default')->first();
                    
                    if ($defaultTenant) {
                        $model->tenant()->associate($defaultTenant);
                    } else {
                        // No tenant context and no default tenant - throw exception
                        throw new InvalidTenantContextException(
                            'No tenant context available and no default tenant found. Please provide a tenant explicitly when creating ' . get_class($model) . '.'
                        );
                    }
                }
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            if (! Schema::hasTable('tenants')) {
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
                $builder->where('tenant_id', $tenant->id);
            } else {
                // If no tenant context is available in HTTP requests, use default tenant
                // to prevent data leakage. API requests should ideally provide tenant via
                // query parameter (?tenant=slug) or header (X-Tenant: slug) for proper isolation.
                $defaultTenant = Tenant::where('slug', 'default')->first();
                
                if ($defaultTenant) {
                    try {
                        $request = app('request');
                        \Log::warning('BelongsToTenant: No tenant context found, using default tenant', [
                            'url' => $request ? $request->fullUrl() : 'N/A',
                            'method' => $request ? $request->method() : 'N/A',
                        ]);
                    } catch (\Throwable $e) {
                        \Log::warning('BelongsToTenant: No tenant context found, using default tenant');
                    }
                    // SECURITY: Use where() instead of whereBelongsTo() to explicitly filter out NULL values
                    $builder->where('tenant_id', $defaultTenant->id);
                } else {
                    // If no default tenant exists, filter to empty result set to prevent data leakage
                    $builder->whereRaw('1 = 0');
                }
            }
        });
    }

    /**
     * Resolve the current tenant from various contexts.
     * 
     * Priority order:
     * 1. Filament tenant context (for admin panel)
     * 2. Request parameter/header (for API requests)
     * 3. Session (for web requests)
     * 
     * @return Tenant|null
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

        // 2. Try to get tenant from HTTP request (for API requests)
        // SECURITY: Only allow tenant override if user is authenticated and has access
        if (app()->runningInConsole() === false) {
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
        if (app()->runningInConsole() === false) {
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
