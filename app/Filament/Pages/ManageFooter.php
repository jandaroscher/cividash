<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasNavigationItemSchema;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;
use App\Settings\FooterSettings;

class ManageFooter extends SettingsPage
{
    use HasNavigationItemSchema;
    protected static string $settings = FooterSettings::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $title = 'Footer';

    /**
     * Builds the form schema for the footer settings page.
     *
     * The returned form contains controls to manage footer navigation items (type-aware page/manual entries
     * with automatic page title and URL resolution), layout configuration (layout type and conditional column count),
     * social links toggling and entries, and copyright text.
     *
     * @return \Filament\Forms\Form The configured form instance for the footer settings.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Repeater::make('footer_navigation_items')
                    ->label(__('filament.pages.manage_footer.footer_navigation_items'))
                    ->schema($this->navigationItemSchema())
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                    ->addActionLabel(__('filament.actions.add')),

                Select::make('layout_type')
                    ->label(__('filament.pages.manage_footer.layout_type'))
                    ->options([
                        'single-row' => __('filament.pages.manage_footer.layout_single_row'),
                        'multi-column' => __('filament.pages.manage_footer.layout_multi_column'),
                        'grid' => __('filament.pages.manage_footer.layout_grid'),
                    ])
                    ->default('single-row')
                    ->required()
                    ->reactive(),

                TextInput::make('columns')
                    ->label(__('filament.pages.manage_footer.columns'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12)
                    ->default(3)
                    ->required(fn ($get) => in_array($get('layout_type'), ['multi-column', 'grid']))
                    ->visible(fn ($get) => in_array($get('layout_type'), ['multi-column', 'grid'])),

                Toggle::make('social_links_enabled')
                    ->label(__('filament.pages.manage_footer.social_links_enabled'))
                    ->default(true),

                TextInput::make('copyright_text')
                    ->label(__('filament.pages.manage_footer.copyright_text'))
                    ->helperText(__('filament.pages.manage_footer.copyright_text_helper'))
                    ->placeholder('© {year} {site_name}')
                    ->nullable(),

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
                    ->reorderable()
                    ->columns(3),
            ]);
    }
}