<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use App\Http\Controllers\Auth\KeycloakSsoController;
use App\Http\Controllers\Auth\PasswordResetRedirectController;
use App\Http\Controllers\PublicMediaController;
use App\Http\Controllers\ResizedMediaController;
use App\Http\Controllers\SpaController;
use App\Http\Controllers\TileShareImageController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Public pages and tile embeds are read-only and need no session. Skipping it means
// anonymous visitors get no cookie and no sessions row (which stores IP and user agent).
$sessionless = [StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class, AddQueuedCookiesToResponse::class];

// Keycloak SSO routes (before SPA catch-all)
Route::get('admin/auth/keycloak/redirect', [KeycloakSsoController::class, 'redirect'])
    ->name('auth.keycloak.redirect');
Route::get('admin/auth/keycloak/callback', [KeycloakSsoController::class, 'callback'])
    ->name('auth.keycloak.callback');

// Password reset route - redirects to Filament admin password reset page.
// Uses an invokable controller (not a Closure) so the full route set stays
// serializable for `php artisan route:cache` in the production container.
Route::get('/reset-password/{token}', PasswordResetRedirectController::class)
    ->name('password.reset');

// RFC 9116 security contact file. Served explicitly (in addition to nginx static
// serving in production) so it also works via `php artisan serve` and is testable.
Route::get('/.well-known/security.txt', fn () => response(
    file_get_contents(public_path('.well-known/security.txt')),
    200,
    ['Content-Type' => 'text/plain']
))->withoutMiddleware($sessionless)->name('security.txt');

// Content images scaled down to a fixed width, for srcset. Before the catch-all below.
Route::get('/storage/_resized/{width}/{path}', ResizedMediaController::class)
    ->whereNumber('width')
    ->where('path', '.*')
    ->withoutMiddleware($sessionless)
    ->name('media.resized');

// Public media (uploads on the 'public' disk), whatever driver backs the disk.
Route::get('/storage/{path}', PublicMediaController::class)
    ->where('path', '.*')
    ->withoutMiddleware($sessionless)
    ->name('media.public');

// The media library's own image route (Glide) is unused: images are served and resized
// above. Registered after the package route, so it replaces it with a 404 instead of a
// server error and keeps Glide from writing a cache outside the media volume.
Route::get('/curator/{path}', fn () => abort(404))
    ->where('path', '.*')
    ->withoutMiddleware($sessionless);

// Open Graph preview image of a public tile, referenced by its og:image tag.
Route::get('/share/tiles/{tile}.png', TileShareImageController::class)
    ->whereNumber('tile')
    ->middleware(['resolve.tenant'])
    ->withoutMiddleware($sessionless)
    ->name('share.tile-image');

// Vue SPA catch-all route
// Excludes API routes, admin routes, and other system routes
// All public routes are now handled by Vue Router
// Uses SpaController to inject server-side OG meta tags for social media crawlers
Route::get('/{any?}', [SpaController::class, 'index'])
    ->where('any', '^(?!api|admin|filament|telescope|horizon|storage|share|embeds|_dusk|tinker).*$')
    ->middleware(['resolve.tenant'])
    ->withoutMiddleware($sessionless)
    ->name('spa');
