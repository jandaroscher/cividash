<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;
use Illuminate\Contracts\Support\Htmlable;

class ManageGeneral extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 20;

    protected static string $settings = GeneralSettings::class;

    public function getTitle(): string|Htmlable
    {
        return __('filament.pages.manage_general.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.pages.manage_general.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.settings');
    }

    /**
     * Build the settings form schema for general site settings.
     *
     * The returned Form is configured with a two-column Grid containing:
     * - a required `site_name` text input (label: filaments.pages.manage_general.site_name)
     * - a `favicon` image file upload (stored on `public/branding`, limited to icon/png/svg types, max size 512, with helper text)
     *
     * @return Form The configured Form instance.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextInput::make('site_name')
                            ->label(__('filament.pages.manage_general.site_name'))
                            ->required(),
                        FileUpload::make('favicon')
                            ->label(__('filament.pages.manage_general.favicon'))
                            ->disk('public')
                            ->directory('branding')
                            ->image()
                            ->acceptedFileTypes(['image/x-icon', 'image/png', 'image/svg+xml', 'image/vnd.microsoft.icon'])
                            ->maxSize(512)
                            ->helperText(__('filament.pages.manage_general.favicon_helper')),
                    ]),
            ]);
    }
}
