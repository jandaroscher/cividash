<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminApiAccess
{
    /**
     * Enforce that the authenticated user's access token has the `admin-api` ability before continuing.
     *
     * Performs these checks and returns a JSON error response if any fail:
     * - Request is authenticated.
     * - If a personal access token is used, the token has the `admin-api` ability.
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

        // If using a personal access token, check for admin-api ability
        $token = $user->currentAccessToken();
        if ($token && method_exists($token, 'can')) {
            if (! $token->can('admin-api')) {
                return response()->json([
                    'message' => 'Token does not have admin-api permission.',
                ], 403);
            }
        }

        return $next($request);
    }
}
