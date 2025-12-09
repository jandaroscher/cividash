<?php

use Illuminate\Support\Facades\Route;

// Vue SPA catch-all route
// Excludes API routes, admin routes, and other system routes
// All public routes are now handled by Vue Router
Route::get('/{any?}', [SpaController::class, 'index'])
    ->where('any', '^(?!api|admin|filament|telescope|horizon|storage|share|embeds|_dusk|tinker).*$')
    ->name('spa');
