<?php

// SPDX-FileCopyrightText: 2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Http\Middleware;

use App\Services\Integration\KeycloakSsoService;
use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * Filament can only require two-factor authentication for the whole panel. This
 * narrows it to users whose dashboards require it and who did not sign in through
 * Keycloak, which enforces its own second factor.
 */
class EnsureTwoFactorAuthenticationWhereRequired extends EnsureMultiFactorAuthenticationIsEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Filament::auth()->user();

        if ($request->session()->get(KeycloakSsoService::SESSION_KEY) || ! $user?->requiresTwoFactorAuthentication()) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
