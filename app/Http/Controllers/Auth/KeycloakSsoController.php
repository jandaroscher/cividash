<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Integration\KeycloakSsoService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class KeycloakSsoController extends Controller
{
    public function __construct(
        protected KeycloakSsoService $ssoService,
    ) {}

    /**
     * Redirect to Keycloak for authentication.
     */
    public function redirect(): RedirectResponse
    {
        abort_unless($this->ssoService->isEnabled(), 403);

        return Socialite::driver('keycloak')->redirect();
    }

    /**
     * Handle the callback from Keycloak after authentication.
     */
    public function callback(): RedirectResponse
    {
        try {
            $socialiteUser = Socialite::driver('keycloak')->user();
        } catch (InvalidStateException) {
            Notification::make()
                ->title(__('filament.sso.login_failed'))
                ->danger()
                ->send();

            return redirect()->to('/admin/login');
        } catch (\Throwable) {
            Notification::make()
                ->title(__('filament.sso.login_failed'))
                ->danger()
                ->send();

            return redirect()->to('/admin/login');
        }

        $user = $this->ssoService->findOrCreateUser($socialiteUser);

        // Sync roles from token claims
        $tokenData = $socialiteUser->user ?? [];
        $this->ssoService->syncRolesFromToken($user, $tokenData);

        // Check if user can access the admin panel
        $panel = Filament::getPanel('admin');
        if (! $user->canAccessPanel($panel)) {
            Notification::make()
                ->title(__('filament.sso.account_inactive'))
                ->danger()
                ->send();

            return redirect()->to('/admin/login');
        }

        Auth::login($user, remember: true);
        session()->regenerate();

        return redirect()->to('/admin');
    }
}
