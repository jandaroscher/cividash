<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTenantResolved
{
    /**
     * Rejects admin requests that lack an explicit, non-default tenant context.
     *
     * Accepts requests only if a tenant was resolved via 'token' or 'domain'; rejects when resolution is missing or was performed via 'default'.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  Closure(Request): (Response)  $next  The next middleware/handler.
     * @return Response The next handler's response, or a 400 JSON error when tenant context is missing or default.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resolvedTenant = $request->attributes->get('resolved_tenant');
        $resolvedBy = $request->attributes->get('resolved_tenant_by');

        // Tenant must be resolved and NOT via default fallback
        if (! $resolvedTenant || $resolvedBy === 'default') {
            return response()->json([
                'message' => 'Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.',
                'error' => 'missing_tenant_context',
            ], 400);
        }

        return $next($request);
    }
}
