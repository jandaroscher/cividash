<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminApiAccess
{
    /**
     * Enforce that the authenticated user (and their access token, if present) is permitted to use the admin API before continuing.
     *
     * Performs these checks and returns a JSON error response if any fail:
     * - Request is authenticated.
     * - The user's `admin_api_enabled` flag is true.
     * - If a personal access token is used, the token has the `admin-api` ability.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next Callable to dispatch the request to the next middleware/handler.
     * @return \Symfony\Component\HttpFoundation\Response The response from the next handler when checks pass, or a JSON error response with HTTP 401/403 when access is denied.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // User must be authenticated (should be handled by auth:sanctum before this)
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Check if user has admin API access enabled
        if (!$user->admin_api_enabled) {
            return response()->json([
                'message' => 'Admin API access not enabled for this user.',
            ], 403);
        }

        // If using a personal access token, check for admin-api ability
        $token = $user->currentAccessToken();
        if ($token && method_exists($token, 'can')) {
            if (!$token->can('admin-api')) {
                return response()->json([
                    'message' => 'Token does not have admin-api permission.',
                ], 403);
            }
        }

        return $next($request);
    }
}