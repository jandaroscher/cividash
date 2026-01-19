<?php

namespace App\Traits;

use Filament\Facades\Filament;

trait GetsTenantCacheKeySegment
{
    /**
     * Get tenant cache key segment for cache key generation.
     * Returns the tenant ID if available, otherwise 'public'.
     *
     * @return string
     */
    protected function getTenantCacheKeySegment(?int $tenantId = null): string
    {
        if ($tenantId) {
            return (string) $tenantId;
        }

        try {
            $filamentTenant = Filament::getTenant();
            if ($filamentTenant) {
                return (string) ($filamentTenant->id ?? $filamentTenant->getKey());
            }
        } catch (\Throwable $e) {
            // Filament tenant not available, continue
        }

        try {
            $request = app('request');
            if ($request && $request->attributes->has('resolved_tenant')) {
                $tenant = $request->attributes->get('resolved_tenant');
                if ($tenant) {
                    return (string) ($tenant->id ?? $tenant->getKey());
                }
            }
        } catch (\Throwable $e) {
            // Ignore request resolution failures
        }

        if (function_exists('tenant')) {
            try {
                $tenant = tenant();
                if ($tenant) {
                    return (string) ($tenant->id ?? $tenant->getKey());
                }
            } catch (\Throwable $e) {
                // fall through to public
            }
        }

        return 'public';
    }
}

