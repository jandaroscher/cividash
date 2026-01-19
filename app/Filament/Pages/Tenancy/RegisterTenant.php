<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\RegisterTenant as BaseRegisterTenant;
use Illuminate\Support\Str;

class RegisterTenant extends BaseRegisterTenant
{
    /**
     * Get the page label displayed in the UI.
     *
     * @return string The translated label for the page.
     */
    public static function getLabel(): string
    {
        return __('Register tenant');
    }

    /**
     * Label used for the navigation menu.
     *
     * @return string The navigation label.
     */
    public static function getNavigationLabel(): string
    {
        return static::getLabel();
    }

    /**
     * Builds the tenant registration form schema.
     *
     * Configures two fields:
     * - `name`: text input labeled "Tenant name", required, maximum length 255, validates on blur, and updates the `slug` field with a slugified value when changed.
     * - `slug`: text input labeled "Slug", required, maximum length 255, unique against the Tenant model (ignores the current record).
     *
     * @param Form $form The form instance to configure.
     * @return Form The configured form instance.
     */
    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('Tenant name'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                    if (! $state) {
                        return;
                    }
                    $set('slug', Str::slug($state));
                }),
            Forms\Components\TextInput::make('slug')
                ->label(__('Slug'))
                ->required()
                ->unique(Tenant::class, ignoreRecord: true)
                ->maxLength(255),
        ]);
    }

    /**
     * Create a new tenant from submitted data, attach it to the current user, and set the user's default tenant when absent.
     *
     * Expects $data to contain 'name' and 'slug'.
     *
     * @param array $data Associative array with keys:
     *                    - 'name' (string): Tenant display name.
     *                    - 'slug' (string): Unique tenant slug.
     * @return \App\Models\Tenant The created Tenant model instance.
     */
    protected function handleRegistration(array $data): Tenant
    {
        return DB::transaction(function () use ($data): Tenant {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
            ]);

            $user = Filament::auth()->user();
            $user->tenants()->syncWithoutDetaching($tenant->id);

            if (! $user->default_tenant_id) {
                $user->forceFill(['default_tenant_id' => $tenant->id])->save();
            }

            return $tenant;
        });
    }
}