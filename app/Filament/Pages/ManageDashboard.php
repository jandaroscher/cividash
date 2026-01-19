<?php

namespace App\Filament\Pages;

use App\Settings\DashboardSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;
use Illuminate\Contracts\Support\Htmlable;

class ManageDashboard extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationGroup = 'Einstellungen';
    protected static ?int $navigationSort = 22;

    protected static string $settings = DashboardSettings::class;

    /**
     * Get the localized title for the settings page.
     *
     * @return string|Htmlable The localized title to display for the page.
     */
    public function getTitle(): string|Htmlable
    {
        return __('filament.pages.manage_dashboard.title');
    }

    /**
     * Provide the localized label for this page's navigation entry.
     *
     * @return string The navigation label translated using the 'filament.pages.manage_dashboard.navigation_label' key.
     */
    public static function getNavigationLabel(): string
    {
        return __('filament.pages.manage_dashboard.navigation_label');
    }

    /**
     * Indicates whether this settings page should be automatically registered in the navigation.
     *
     * @return bool `true` to register the page in the navigation, `false` otherwise.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * Builds the settings form schema for the dashboard management page.
     *
     * @param \Filament\Forms\Form $form The form instance to configure.
     * @return \Filament\Forms\Form The configured form with sections and fields for content links, contact details, and server display options.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('filament.pages.manage_dashboard.content_links_section'))
                    ->schema([
                        TextInput::make('open_source_docs_url')
                            ->label(__('filament.pages.manage_dashboard.open_source_docs_url'))
                            ->placeholder('https://example.com/docs')
                            ->nullable()
                            ->maxLength(255)
                            ->url(),
                        TextInput::make('user_manual_url')
                            ->label(__('filament.pages.manage_dashboard.user_manual_url'))
                            ->placeholder('https://example.com/manual')
                            ->nullable()
                            ->maxLength(255)
                            ->url(),
                    ])
                    ->columns(2),
                Section::make(__('filament.pages.manage_dashboard.contact_section'))
                    ->schema([
                        TextInput::make('contact_name')
                            ->label(__('filament.pages.manage_dashboard.contact_name'))
                            ->nullable()
                            ->maxLength(255),
                        TextInput::make('contact_email')
                            ->label(__('filament.pages.manage_dashboard.contact_email'))
                            ->nullable()
                            ->maxLength(255)
                            ->email(),
                        TextInput::make('contact_url')
                            ->label(__('filament.pages.manage_dashboard.contact_url'))
                            ->placeholder('https://example.com/contact')
                            ->nullable()
                            ->maxLength(255)
                            ->url(),
                        TextInput::make('made_with_text')
                            ->label(__('filament.pages.manage_dashboard.made_with_text'))
                            ->placeholder('Made with ❤️ in Demo City')
                            ->nullable()
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make(__('filament.pages.manage_dashboard.server_section'))
                    ->schema([
                        Toggle::make('show_server_time')
                            ->label(__('filament.pages.manage_dashboard.show_server_time')),
                    ]),
            ]);
    }
}