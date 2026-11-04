<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Http\Requests\Auth;

use App\Services\Integration\KeycloakSsoService;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Fortify\Http\Requests\LoginRequest;

/**
 * Fortify's JSON login, refused while only SSO login is allowed.
 */
class PasswordLoginRequest extends LoginRequest
{
    public function authorize(): bool
    {
        return app(KeycloakSsoService::class)->passwordLoginAllowed();
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('auth.password_login_disabled'));
    }
}
