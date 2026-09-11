<?php

use App\Console\Commands\DashboardSeedCommand;
use App\Console\Commands\PagesSeedCommand;
use App\Console\Commands\TenancyBackfillCommand;
use App\Http\Middleware\EnsureAdminApiAccess;
use App\Http\Middleware\EnsureAdminTenantResolved;
use App\Http\Middleware\LocaleDetector;
use App\Http\Middleware\ResolveTenantFromRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        TenancyBackfillCommand::class,
        PagesSeedCommand::class,
        DashboardSeedCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // API guests get a 401 (rendered as JSON below); the SPA has no route('login').
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/admin/login');

        // Trust the reverse proxy/ingress in front of the app (APISIX, nginx, etc.)
        // so X-Forwarded-* headers are honored: domain-based tenant resolution relies
        // on $request->getHost() (X-Forwarded-Host) and correct HTTPS URL generation
        // depends on X-Forwarded-Proto/Port.
        // TRUSTED_PROXIES='*' trusts all proxies (safe for in-cluster traffic already
        // filtered by the ingress); a comma-separated CIDR list (e.g.
        // "10.0.0.0/8,172.16.0.0/12") is split into individual entries.
        $trustedProxies = env('TRUSTED_PROXIES', '*');
        $middleware->trustProxies(
            at: $trustedProxies === '*' ? '*' : array_map('trim', explode(',', $trustedProxies)),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->web(append: [
            LocaleDetector::class,
        ]);

        $middleware->alias([
            'admin.api' => EnsureAdminApiAccess::class,
            'admin.tenant' => EnsureAdminTenantResolved::class,
            'resolve.tenant' => ResolveTenantFromRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // The SPA has no route('login'), so a guest hitting /api/* without a JSON
        // Accept header must get a 401 body instead of a redirect attempt (500).
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
