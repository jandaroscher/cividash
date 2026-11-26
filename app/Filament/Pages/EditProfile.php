<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Pages;

use App\Services\Integration\KeycloakSsoService;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class EditProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'profile';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $profileData = [];

    public ?array $passwordData = [];

    public function getTitle(): string
    {
        return __('filament.pages.edit_profile.title');
    }

    public function mount(): void
    {
        $user = Auth::user();

        $this->profileForm->fill([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_path' => $user->avatar_path,
            'locale' => $user->locale ?? 'de',
        ]);

        $this->passwordForm->fill();
    }

    protected function getForms(): array
    {
        return [
            'profileForm',
            'passwordForm',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->formWithSubmit('profileForm', 'updateProfile', __('filament.pages.edit_profile.save_profile')),
            $this->formWithSubmit('passwordForm', 'updatePassword', __('filament.pages.edit_profile.save_password')),
            $this->twoFactorAuthenticationSection(),
        ]);
    }

    /**
     * Same management UI as Filament's built-in profile page, which this page replaces.
     */
    private function twoFactorAuthenticationSection(): Section
    {
        return Section::make(__('filament.pages.edit_profile.two_factor_authentication'))
            ->description(__('filament.pages.edit_profile.two_factor_authentication_description'))
            // Keycloak enforces its own second factor for SSO logins.
            ->hidden(fn (): bool => KeycloakSsoService::signedInViaKeycloak())
            ->schema(collect(Filament::getMultiFactorAuthenticationProviders())
                ->map(fn (MultiFactorAuthenticationProvider $provider): Group => Group::make($provider->getManagementSchemaComponents())
                    ->statePath($provider->getId()))
                ->all());
    }

    private function formWithSubmit(string $form, string $method, string $label): Form
    {
        return Form::make([EmbeddedSchema::make($form)])
            ->id($form)
            ->livewireSubmitHandler($method)
            ->footer([
                Actions::make([Action::make($method)->label($label)->submit($method)])->key("{$form}-actions"),
            ]);
    }

    public function profileForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.pages.edit_profile.profile_information'))
                    ->description(__('filament.pages.edit_profile.profile_information_description'))
                    ->schema([
                        FileUpload::make('avatar_path')
                            ->label(__('filament.pages.edit_profile.avatar'))
                            ->image()
                            ->avatar()
                            ->disk('public')
                            ->directory('avatars')
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth('256')
                            ->imageResizeTargetHeight('256')
                            ->maxSize(2048)
                            ->columnSpanFull(),

                        TextInput::make('first_name')
                            ->label(__('filament.pages.edit_profile.first_name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('last_name')
                            ->label(__('filament.pages.edit_profile.last_name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label(__('filament.pages.edit_profile.email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique('users', 'email', ignorable: Auth::user()),

                        TextInput::make('phone')
                            ->label(__('filament.pages.edit_profile.phone'))
                            ->tel()
                            ->maxLength(255),

                        Select::make('locale')
                            ->label(__('filament.pages.edit_profile.locale'))
                            ->options([
                                'de' => 'Deutsch',
                                'en' => 'English',
                            ])
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),
            ])
            ->statePath('profileData');
    }

    public function passwordForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.pages.edit_profile.update_password'))
                    ->description(__('filament.pages.edit_profile.update_password_description'))
                    ->schema([
                        TextInput::make('current_password')
                            ->label(__('filament.pages.edit_profile.current_password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->currentPassword(),

                        TextInput::make('password')
                            ->label(__('filament.pages.edit_profile.new_password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::defaults())
                            ->autocomplete('new-password')
                            ->dehydrateStateUsing(fn ($state): string => Hash::make($state))
                            ->live(debounce: 500)
                            ->same('password_confirmation'),

                        TextInput::make('password_confirmation')
                            ->label(__('filament.pages.edit_profile.password_confirmation'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->dehydrated(false),
                    ]),
            ])
            ->statePath('passwordData');
    }

    public function updateProfile(): void
    {
        $data = $this->profileForm->getState();

        $user = Auth::user();
        $user->update($data);

        Notification::make()
            ->title(__('filament.pages.edit_profile.profile_updated'))
            ->success()
            ->send();
    }

    public function updatePassword(): void
    {
        $data = $this->passwordForm->getState();

        $user = Auth::user();
        $user->update([
            'password' => $data['password'],
        ]);

        $this->passwordForm->fill();

        Notification::make()
            ->title(__('filament.pages.edit_profile.password_updated'))
            ->success()
            ->send();
    }
}
