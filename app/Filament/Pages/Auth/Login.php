<?php

namespace App\Filament\Pages\Auth;

use App\Services\Integration\KeycloakSsoService;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

/**
 * Admin login that hides the password form when only SSO is allowed; the
 * Keycloak button is added by a render hook in AdminPanelProvider.
 */
class Login extends BaseLogin
{
    protected function getForms(): array
    {
        if ($this->passwordLoginAllowed()) {
            return parent::getForms();
        }

        return [
            'form' => $this->form($this->makeForm()->schema([])->statePath('data')),
        ];
    }

    protected function getFormActions(): array
    {
        return $this->passwordLoginAllowed() ? parent::getFormActions() : [];
    }

    public function authenticate(): ?LoginResponse
    {
        // Also guard the Livewire action itself, not only the rendered form.
        if (! $this->passwordLoginAllowed()) {
            throw ValidationException::withMessages([
                'data.email' => __('auth.password_login_disabled'),
            ]);
        }

        return parent::authenticate();
    }

    private function passwordLoginAllowed(): bool
    {
        return app(KeycloakSsoService::class)->passwordLoginAllowed();
    }
}
