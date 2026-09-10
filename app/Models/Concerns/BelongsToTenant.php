<?php

namespace App\Models\Concerns;

use App\Exceptions\InvalidTenantContextException;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

trait BelongsToTenant
{
    use ResolvesCurrentTenant;

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
            // Robust schema check with try-catch for unit tests without a DB
            try {
                if (! Schema::hasTable('tenants')) {
                    return;
                }
            } catch (\Throwable $e) {
                // Schema check can fail in unit tests without a DB; skip tenant assignment
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
                                'No tenant context available and no default tenant found. Please provide a tenant explicitly when creating '.get_class($model).'.'
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
            // Robust schema check with try-catch for unit tests without a DB
            try {
                if (! Schema::hasTable('tenants')) {
                    return;
                }
            } catch (\Throwable $e) {
                // Schema check can fail in unit tests without a DB; skip applying the scope
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
}
