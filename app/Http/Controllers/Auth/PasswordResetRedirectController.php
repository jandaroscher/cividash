<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasswordResetRedirectController extends Controller
{
    /**
     * Redirect a password-reset link to the Filament admin password-reset page,
     * preserving any query string (e.g. the signed email parameter).
     *
     * Replaces a previous Closure route so the route set remains serializable
     * for `php artisan route:cache`.
     */
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $queryString = $request->getQueryString();

        return redirect()->to('/admin/password-reset/'.$token.($queryString ? '?'.$queryString : ''));
    }
}
