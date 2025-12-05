<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasNavigationItemSchema;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;
use App\Settings\HeaderSettings;

class ManageHeader extends SettingsPage
{
    use HasNavigationItemSchema;

    protected static string $settings = HeaderSettings::class;
    protected static ?string $navigationIcon = 'heroicon-o-bars-3';
    protected static ?string $title = 'Header / Navigation';
    protected static ?string $navigationLabel = 'Header / Navigation';

    /**
     * Builds the form schema used to configure header/navigation items and related settings.
     *
     * @param Form $form The form instance to configure.
     * @return Form The configured form containing a navigation_items repeater (with nested children, page/manual selection, auto-populated labels/URLs), and the show_language_switcher and dropdown_enabled checkboxes.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Repeater::make('navigation_items')
                    ->label(__('filament.pages.manage_header.navigation_items'))
                    ->schema([
                        ...$this->navigationItemSchema(),

                        Repeater::make('children')
                            ->label(__('filament.pages.manage_header.children'))
                            ->schema($this->navigationItemSchema())
                            ->visible(fn ($get) => $get('../../dropdown_enabled') === true)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->reorderable(),
                    ])
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                    ->addActionLabel(__('filament.actions.add')),

                Toggle::make('show_language_switcher')
                    ->label(__('filament.pages.manage_header.show_language_switcher'))
                    ->default(true),

                Toggle::make('dropdown_enabled')
                    ->label(__('filament.pages.manage_header.dropdown_enabled'))
                    ->helperText(__('filament.pages.manage_header.dropdown_enabled_helper'))
                    ->default(false)
                    ->reactive(),
            ]);
    }
}
