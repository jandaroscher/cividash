<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile as BaseEditTenantProfile;

class EditTenantProfile extends BaseEditTenantProfile
{
    /**
     * Page label displayed for the tenant profile page.
     *
     * @return string The translated label for the tenant profile page.
     */
    public static function getLabel(): string
    {
        return __('Tenant profile');
    }

    /**
     * Provides the label shown in the navigation for this page.
     *
     * @return string The navigation label.
     */
    public static function getNavigationLabel(): string
    {
        return static::getLabel();
    }

    /**
     * Builds the form schema used to edit a tenant's profile.
     *
     * Configures fields for tenant name, slug (unique, ignores current record), and optional
     * theme configuration as key/value pairs.
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
                ->maxLength(255),
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
     * Retrieve the current tenant model for the active Filament tenant context.
     *
     * @return Tenant The Tenant model instance for the current tenant.
     */
    protected function resolveRecord(): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();

        return $tenant;
    }
}
