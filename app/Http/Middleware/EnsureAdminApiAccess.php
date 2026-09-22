<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminApiAccess
{
    /**
     * Enforce admin-level access to the admin API before continuing.
     *
     * Performs these checks and returns a JSON error response if any fail:
     * - Request is authenticated.
     * - A personal access token (Sanctum PAT) must carry the `admin-api` ability.
     * - A session-authenticated user (Sanctum's TransientToken, which answers
     *   every ability check with true) must instead have `is_admin` set,
     *   mirroring the restriction the Filament UI already applies to these pages.
     *
     * @param  Closure(Request): (Response)  $next  Callable to dispatch the request to the next middleware/handler.
     * @return Response The response from the next handler when checks pass, or a JSON error response with HTTP 401/403 when access is denied.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // User must be authenticated (should be handled by auth:sanctum before this)
        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            if (! $token->can('admin-api')) {
                return response()->json([
                    'message' => 'Token does not have admin-api permission.',
                ], 403);
            }
        } elseif (! $user->is_admin) {
            // No PAT means session/cookie auth (Sanctum's TransientToken), whose
            // can() always returns true. Fall back to the admin role instead.
            return response()->json([
                'message' => 'Admin role required.',
            ], 403);
        }

        return $next($request);
    }
}
