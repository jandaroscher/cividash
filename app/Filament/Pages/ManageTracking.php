<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Pages;

use App\Settings\TrackingSettings;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ManageTracking extends SettingsPage
{
    protected static string $settings = TrackingSettings::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 41;

    protected static ?string $slug = 'tracking';

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.pages.manage_tracking.navigation_label');
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament.pages.manage_tracking.title');
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->is_admin;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.pages.manage_tracking.matomo_heading'))
                    ->description(__('filament.pages.manage_tracking.matomo_description'))
                    ->schema([
                        TextInput::make('matomo_url')
                            ->label(__('filament.pages.manage_tracking.matomo_url'))
                            ->helperText(__('filament.pages.manage_tracking.matomo_url_help'))
                            ->url()
                            // The URL becomes a script source on every public page, so only HTTPS.
                            ->startsWith(['https://'])
                            // No query or fragment: the script path is appended to this URL.
                            ->regex('/^https:\/\/[^?#\s]+$/')
                            ->maxLength(255)
                            ->requiredWith('matomo_site_id')
                            ->placeholder('https://matomo.example.org/'),

                        TextInput::make('matomo_site_id')
                            ->label(__('filament.pages.manage_tracking.matomo_site_id'))
                            ->helperText(__('filament.pages.manage_tracking.matomo_site_id_help'))
                            ->regex('/^[1-9][0-9]{0,9}$/')
                            ->required(fn (Get $get): bool => filled($get('matomo_url')))
                            ->placeholder('1'),
                    ])
                    ->columns(2),
            ]);
    }
}
