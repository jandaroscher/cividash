<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Pages\Auth;

use App\Services\Integration\KeycloakSsoService;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
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

    // Filament sets tabindex 1 and 2 on the fields; a positive tabindex pulls
    // them out of the document tab order, which screen readers flag.
    protected function getEmailFormComponent(): Component
    {
        /** @var TextInput $component */
        $component = parent::getEmailFormComponent();

        return $component->extraInputAttributes([]);
    }

    protected function getPasswordFormComponent(): Component
    {
        /** @var TextInput $component */
        $component = parent::getPasswordFormComponent();

        return $component->extraInputAttributes([]);
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
