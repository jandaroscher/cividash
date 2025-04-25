<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;
use App\Settings\FooterSettings;

class ManageFooter extends SettingsPage
{
    protected static string $settings = FooterSettings::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $title = 'Footer Settings';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Repeater::make('footer_links')
                    ->label('Footer Links')
                    ->schema([
                        TextInput::make('label')
                            ->label('Link Label'),
                        TextInput::make('url')
                            ->label('Link URL')
                            ->url()
                            ->required(),
                    ])
                    ->orderable()
                    ->columns(2),

                Repeater::make('footer_logos')
                    ->label('Footer Logos')
                    ->schema([
                        FileUpload::make('logo_url')
                            ->label('Logo Image')
                            ->image()
                            ->directory('footer-logos')
                            ->required(),
                        TextInput::make('link')
                            ->label('Destination URL')
                            ->url(),
                        TextInput::make('title')
                            ->label('Alt / Tooltip Text'),
                    ])
                    ->orderable()
                    ->columns(3),

                Repeater::make('social_links')
                    ->label('Social Media Links')
                    ->schema([
                        FileUpload::make('icon')
                            ->label('Icon')
                            ->image()
                            ->directory('footer-social-icons')
                            ->required(),
                        TextInput::make('link')
                            ->label('Profile URL')
                            ->url()
                            ->required(),
                        TextInput::make('title')
                            ->label('Tooltip Text'),
                    ])
                    ->orderable()
                    ->columns(3),
            ]);
    }
}
