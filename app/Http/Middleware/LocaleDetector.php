<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LocaleDetector
{
    /**
     * Handle an incoming request.
     * Detects locale from URI prefix (/en/) for public routes.
     * For admin routes, uses the authenticated user's locale preference.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        // For admin routes, respect language switcher (session/cookie), fall back to user's DB locale
        if (str_starts_with($path, 'admin')) {
            $user = Auth::user();
            $locale = session('locale')
                ?? request()->cookie('filament_language_switch_locale')
                ?? $user?->locale
                ?? 'de';
            App::setLocale($locale);

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
