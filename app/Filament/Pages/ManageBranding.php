<?php

namespace App\Filament\Pages;

use App\Settings\BrandingSettings;
use Filament\Forms;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

class ManageBranding extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $title = 'Seiteneinstellungen';

    protected static string $settings = BrandingSettings::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                ColorPicker::make('primary_color')
                    ->label(__('filament.pages.manage_branding.primary_color'))
                    ->required(),

                ColorPicker::make('secondary_color')
                    ->label(__('filament.pages.manage_branding.secondary_color'))
                    ->required(),

                FileUpload::make('logo_url')
                ->label(__('filament.pages.manage_branding.logo'))
                    ->disk('public')
                    ->directory('branding')
                    ->image()
                    ->preserveFilenames()
                    ->required(false),
            ]);
    }
}
