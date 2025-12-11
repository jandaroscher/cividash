<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile as BaseEditTenantProfile;

class EditTenantProfile extends BaseEditTenantProfile
{
    public static function getLabel(): string
    {
        return __('Tenant profile');
    }

    public static function getNavigationLabel(): string
    {
        return static::getLabel();
    }

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

    protected function resolveRecord(): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();

        return $tenant;
    }
}
