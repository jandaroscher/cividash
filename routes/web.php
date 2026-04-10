<?php

use App\Http\Controllers\Auth\KeycloakSsoController;
use Illuminate\Support\Facades\Route;

// Keycloak SSO routes (before SPA catch-all)
Route::get('admin/auth/keycloak/redirect', [KeycloakSsoController::class, 'redirect'])
    ->name('auth.keycloak.redirect');
Route::get('admin/auth/keycloak/callback', [KeycloakSsoController::class, 'callback'])
    ->name('auth.keycloak.callback');

// Password reset route - redirects to Filament admin password reset page
Route::get('/reset-password/{token}', function (string $token) {
    $queryString = request()->getQueryString();

    return redirect()->to('/admin/password-reset/'.$token.($queryString ? '?'.$queryString : ''));
})->name('password.reset');

// Vue SPA catch-all route
// Excludes API routes, admin routes, and other system routes
// All public routes are now handled by Vue Router
Route::get('/{any?}', [SpaController::class, 'index'])
    ->where('any', '^(?!api|admin|filament|telescope|horizon|storage|share|embeds|_dusk|tinker).*$')
    ->name('spa');
