<?php

namespace App\Traits;

trait GetsTenantCacheKeySegment
{
    /**
     * Get tenant cache key segment for cache key generation.
     * Returns the tenant ID if available, otherwise 'public'.
     *
     * @return string
     */
    protected function getTenantCacheKeySegment(): string
    {
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

