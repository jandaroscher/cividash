<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks a response as served through the deprecated unversioned /api/* alias
 * and points callers at the versioned /api/v1 equivalent. Applied only to the
 * alias route registration, never to /api/v1 itself.
 *
 * No Sunset header: the alias has no scheduled removal date yet.
 */
class AddDeprecatedApiAliasHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $successorPath = '/api/v1'.Str::after($request->path(), 'api');
        $successorUrl = $request->getSchemeAndHttpHost().$successorPath;
        if ($query = $request->getQueryString()) {
            $successorUrl .= '?'.$query;
        }

        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Link', '<'.$successorUrl.'>; rel="successor-version"');

        return $response;
    }
}
