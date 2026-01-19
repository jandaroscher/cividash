<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class LocaleDetector
{
    /**
     * Handle an incoming request.
     * Detects locale from URI prefix (/en/) and sets it via App::setLocale().
     * Excludes admin routes to avoid interfering with Filament's locale handling.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        
        // Ensure admin uses German UI labels by default
        if (str_starts_with($path, 'admin')) {
            App::setLocale('de');
            return $next($request);
        }
        
        // Check if path is exactly 'en' or starts with 'en/'
        if ($path === 'en' || str_starts_with($path, 'en/')) {
            App::setLocale('en');
        } else {
            // Default to German (de)
            App::setLocale('de');
        }

        return $next($request);
    }
}
