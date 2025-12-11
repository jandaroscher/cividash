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
     * Builds and returns the tenant registration form schema.
     *
     * Configures three fields:
     * - `name`: text input labeled "Tenant name", required, max length 255, live validation on blur, and updates the `slug` field with a slugified version when changed.
     * - `slug`: text input labeled "Slug", required, unique against the Tenant model (ignores current record), max length 255.
     * - `theme_config`: optional key-value input labeled "Theme configuration (optional)" with custom key/value labels and an "Add setting" button.
     *
     * @param Form $form The form instance to configure.
     * @return Form The form instance populated with the tenant registration schema.
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
            Forms\Components\KeyValue::make('theme_config')
                ->label(__('Theme configuration (optional)'))
                ->keyLabel(__('Key'))
                ->valueLabel(__('Value'))
                ->addButtonLabel(__('Add setting'))
                ->nullable(),
        ]);
    }

    /**
     * Create a new tenant from submitted data, attach it to the current user, and set the user's default tenant when absent.
     *
     * Expects $data to contain 'name' and 'slug'; 'theme_config' is optional and will be stored or set to null.
     *
     * @param array $data Associative array with keys:
     *                    - 'name' (string): Tenant display name.
     *                    - 'slug' (string): Unique tenant slug.
     *                    - 'theme_config' (array|null) Optional theme configuration.
     * @return \App\Models\Tenant The created Tenant model instance.
     */
    protected function handleRegistration(array $data): Tenant
    {
        return DB::transaction(function () use ($data): Tenant {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'theme_config' => $data['theme_config'] ?? null,
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
