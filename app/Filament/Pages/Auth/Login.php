<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Pages\Auth;

use App\Services\Integration\KeycloakSsoService;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Admin login that hides the password form when only SSO is allowed; the
 * Keycloak button is added by a render hook in AdminPanelProvider.
 */
class Login extends \Filament\Auth\Pages\Login
{
    public function form(Schema $schema): Schema
    {
        if ($this->passwordLoginAllowed()) {
            return parent::form($schema);
        }

        return $schema->components([]);
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
