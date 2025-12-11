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
    public static function getLabel(): string
    {
        return __('Register tenant');
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

    protected function handleRegistration(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {
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
