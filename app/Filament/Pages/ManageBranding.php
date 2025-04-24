<?php

namespace App\Filament\Pages;

use App\Settings\BrandingSettings;
use Filament\Forms;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

class ManageBranding extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $settings = BrandingSettings::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                ColorPicker::make('primary_color')
                    ->label('Primary Color')
                    ->required(),

                ColorPicker::make('secondary_color')
                    ->label('Secondary Color')
                    ->required(),

                TextInput::make('logo_url')
                    ->label('Logo URL')
                    ->url()
            ]);
    }
}
