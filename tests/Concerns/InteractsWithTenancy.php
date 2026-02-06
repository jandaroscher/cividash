<?php

namespace Tests\Concerns;

use App\Models\Tenant;
use Filament\Facades\Filament;

trait InteractsWithTenancy
{
    /**
     * Create a Tenant model for tests with sensible defaults.
     *
     * Merges the provided `$attributes` with default values (`name` => "Test Tenant", `slug` => "test-tenant`)
     * and persists the resulting Tenant.
     *
     * @param  array  $attributes  Attributes to override the defaults (e.g. 'name', 'slug').
     * @return Tenant The created Tenant instance.
     */
    protected function createTenant(array $attributes = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ], $attributes));
    }

    /**
     * Set the current Filament tenant context for tests.
     *
     * @param  Tenant  $tenant  The tenant to set as the active Filament context.
     */
    protected function setTenantContext(Tenant $tenant): void
    {
        Filament::setTenant($tenant);
    }

    /**
     * Execute the given callback under the provided tenant context and restore the previous tenant afterwards.
     *
     * If no previous tenant was set, the tenant context is cleared after execution.
     *
     * @param  Tenant  $tenant  The tenant to set for the duration of the callback.
     * @param  callable  $callback  The callback to execute within the tenant context.
     * @return mixed The value returned by the callback.
     */
    protected function withTenant(Tenant $tenant, callable $callback)
    {
        $previous = Filament::getTenant();
        try {
            Filament::setTenant($tenant);

            return $callback();
        } finally {
            if ($previous) {
                Filament::setTenant($previous);
            } else {
                Filament::setTenant(null);
            }
        }
    }

    /**
     * Run the given callback with the Tenant model's 'tenant' global scope disabled.
     *
     * @param  callable  $callback  The callback to execute without the tenant scope.
     * @return mixed The value returned by the callback.
     */
    protected function withoutTenantScope(callable $callback)
    {
        $builder = \App\Models\Tenant::withoutGlobalScope('tenant');

        return $callback($builder);
    }
}
