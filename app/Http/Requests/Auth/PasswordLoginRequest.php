<?php

namespace App\Http\Requests\Auth;

use App\Services\Integration\KeycloakSsoService;
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
}
