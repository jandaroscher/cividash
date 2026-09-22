<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Integration\KeycloakSsoService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        abort_unless($this->ssoService->isEnabled(), 403);

        try {
            // In containerized environments (e.g. DDEV), the server cannot reach
            // Keycloak via the browser-facing URL. Override for server-side calls.
            if ($internalUrl = config('services.keycloak.base_url_internal')) {
                config(['services.keycloak.base_url' => $internalUrl]);
            }

            $socialiteUser = Socialite::driver('keycloak')->user();
            $user = $this->ssoService->findOrCreateUser($socialiteUser);

            // Reject before syncRolesFromToken() below can mutate is_admin
            // (or assign tenant roles) on an account that is denied anyway.
            if (! $user->canAccessPanel(Filament::getPanel('admin'))) {
                Notification::make()
                    ->title(__('filament.sso.account_inactive'))
                    ->danger()
                    ->send();

                return redirect()->to('/admin/login');
            }
        } catch (InvalidStateException $e) {
            Log::error('Keycloak SSO: InvalidStateException', ['class' => get_class($e)]);
            Notification::make()
                ->title(__('filament.sso.login_failed'))
                ->danger()
                ->send();

            return redirect()->to('/admin/login');
        } catch (\Throwable $e) {
            Log::error('Keycloak SSO: callback error', ['class' => get_class($e)]);
            Notification::make()
                ->title(__('filament.sso.login_failed'))
                ->danger()
                ->send();

            return redirect()->to('/admin/login');
        }

        // Sync roles from token claims
        $tokenData = $socialiteUser->user ?? [];
        $this->ssoService->syncRolesFromToken($user, $tokenData);

        Auth::login($user, remember: true);
        session()->regenerate();

        return redirect()->to('/admin');
    }
}
