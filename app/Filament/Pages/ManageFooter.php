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
    protected static ?string $title = 'Seiteneinstellungen';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Repeater::make('footer_links')
                    ->label(__('filament.pages.manage_footer.footer_links'))
                    ->schema([
                        TextInput::make('label')
                            ->label(__('filament.pages.manage_footer.link_label')),
                        TextInput::make('url')
                            ->label(__('filament.pages.manage_footer.link_url'))
                            ->url()
                            ->required(),
                    ])
                    ->orderable()
                    ->columns(2),

                Repeater::make('footer_logos')
                    ->label(__('filament.pages.manage_footer.footer_logos'))
                    ->schema([
                        FileUpload::make('logo_url')
                            ->label(__('filament.pages.manage_footer.logo_image'))
                            ->image()
                            ->directory('footer-logos')
                            ->required(),
                        TextInput::make('link')
                            ->label(__('filament.pages.manage_footer.destination_url'))
                            ->url(),
                        TextInput::make('title')
                            ->label(__('filament.pages.manage_footer.alt_tooltip_text')),
                    ])
                    ->orderable()
                    ->columns(3),

                Repeater::make('social_links')
                    ->label(__('filament.pages.manage_footer.social_media_links'))
                    ->schema([
                        FileUpload::make('icon')
                            ->label(__('filament.pages.manage_footer.icon'))
                            ->image()
                            ->directory('footer-social-icons')
                            ->required(),
                        TextInput::make('link')
                            ->label(__('filament.pages.manage_footer.profile_url'))
                            ->url()
                            ->required(),
                        TextInput::make('title')
                            ->label(__('filament.pages.manage_footer.tooltip_text')),
                    ])
                    ->orderable()
                    ->columns(3),
            ]);
    }
}
