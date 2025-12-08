<?php

namespace App\Filament\Pages;

use App\Settings\BrandingSettings;
use Filament\Forms;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

class ManageBranding extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';
    protected static ?string $title = 'Theme';
    protected static ?string $navigationLabel = 'Theme';
    protected static ?string $navigationGroup = 'Einstellungen';
    protected static ?int $navigationSort = 21;

    protected static string $settings = BrandingSettings::class;

    /**
     * Builds the branding settings form schema for the Theme page.
     *
     * Configures sections for colors, logo, typography, font sizes, background colors, text colors, border/shadow colors, and slider colors.
     *
     * @param Form $form The form instance to configure.
     * @return Form The configured form instance containing the Theme branding settings schema.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('filament.pages.manage_branding.colors_section'))
                    ->schema([
                        ColorPicker::make('primary_color')
                            ->label(__('filament.pages.manage_branding.primary_color'))
                            ->required(),

                        ColorPicker::make('secondary_color')
                            ->label(__('filament.pages.manage_branding.secondary_color'))
                            ->required(),

                        ColorPicker::make('accent_color')
                            ->label(__('filament.pages.manage_branding.accent_color'))
                            ->helperText(__('filament.pages.manage_branding.accent_color_helper'))
                            ->required(false),
                    ])
                    ->columns(3),

                Forms\Components\Section::make(__('filament.pages.manage_branding.logo_section'))
                    ->schema([
                        FileUpload::make('logo_url')
                            ->label(__('filament.pages.manage_branding.logo'))
                            ->disk('public')
                            ->directory('branding')
                            ->image()
                            ->preserveFilenames()
                            ->required(false),
                    ]),

                Forms\Components\Section::make(__('filament.pages.manage_branding.typography_section'))
                    ->schema([
                        Select::make('typography_font_family')
                            ->label(__('filament.pages.manage_branding.typography_font_family'))
                            ->options([
                                'Open Sans' => 'Open Sans',
                                'Roboto' => 'Roboto',
                                'Inter' => 'Inter',
                                'Lato' => 'Lato',
                                'Montserrat' => 'Montserrat',
                                'Poppins' => 'Poppins',
                                'Source Sans Pro' => 'Source Sans Pro',
                            ])
                            ->default('Open Sans')
                            ->required(fn ($get) => empty($get('typography_custom_font_file')))
                            ->searchable()
                            ->reactive()
                            ->hidden(fn ($get) => !empty($get('typography_custom_font_file'))),

                        Repeater::make('typography_font_weights')
                            ->label(__('filament.pages.manage_branding.typography_font_weights'))
                            ->schema([
                                Select::make('weight')
                                    ->options([
                                        100 => '100 (Thin)',
                                        200 => '200 (Extra Light)',
                                        300 => '300 (Light)',
                                        400 => '400 (Regular)',
                                        500 => '500 (Medium)',
                                        600 => '600 (Semi Bold)',
                                        700 => '700 (Bold)',
                                        800 => '800 (Extra Bold)',
                                        900 => '900 (Black)',
                                    ])
                                    ->required(),
                            ])
                            ->defaultItems(3)
                            ->itemLabel(fn (array $state): ?string => $state['weight'] ?? null)
                            ->collapsible()
                            ->reorderable()
                            ->addActionLabel(__('filament.actions.add'))
                            ->afterStateHydrated(function ($component, $state) {
                                // Convert simple array [400, 600, 700] to repeater format [{'weight': 400}, ...]
                                if (is_array($state) && !empty($state) && isset($state[0]) && is_numeric($state[0])) {
                                    $component->state(array_map(fn($w) => ['weight' => (int)$w], $state));
                                }
                            })
                            ->mutateDehydratedStateUsing(function ($state) {
                                // Convert repeater format back to simple array [400, 600, 700]
                                if (!is_array($state)) {
                                    return $state;
                                }
                                
                                $weights = [];
                                foreach ($state as $item) {
                                    // Skip non-array items or items without 'weight' key
                                    if (!is_array($item) || !isset($item['weight'])) {
                                        continue;
                                    }
                                    
                                    // Coerce numeric strings to int, skip non-numeric values
                                    $weight = $item['weight'];
                                    if (is_numeric($weight)) {
                                        $weights[] = (int)$weight;
                                    }
                                }
                                
                                return array_values($weights);
                            })
                            ->required(fn ($get) => empty($get('typography_custom_font_file')))
                            ->hidden(fn ($get) => !empty($get('typography_custom_font_file'))),

                        Forms\Components\Group::make([
                            FileUpload::make('typography_custom_font_file')
                                ->label(__('filament.pages.manage_branding.custom_font_file'))
                                ->disk('public')
                                ->directory('fonts/custom')
                                ->acceptedFileTypes(['application/font-woff2', 'application/font-woff', 'font/woff2', 'font/woff', 'application/x-font-ttf', 'font/ttf', 'application/x-font-opentype', 'font/otf'])
                                ->maxSize(5120) // 5MB
                                ->helperText(__('filament.pages.manage_branding.custom_font_file_helper'))
                                ->nullable()
                                ->reactive()
                                ->hidden(fn ($get) => empty($get('typography_font_family')) && empty($get('typography_custom_font_file'))),

                            TextInput::make('typography_custom_font_name')
                                ->label(__('filament.pages.manage_branding.custom_font_name'))
                                ->helperText(__('filament.pages.manage_branding.custom_font_name_helper'))
                                ->required(fn ($get) => !empty($get('typography_custom_font_file')))
                                ->nullable()
                                ->hidden(fn ($get) => empty($get('typography_font_family')) && empty($get('typography_custom_font_file'))),
                        ])
                        ->columns(2)
                        ->hidden(fn ($get) => empty($get('typography_font_family')) && empty($get('typography_custom_font_file'))),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('filament.pages.manage_branding.font_sizes_section'))
                    ->schema([
                        KeyValue::make('typography_font_sizes')
                            ->label(__('filament.pages.manage_branding.font_sizes'))
                            ->keyLabel(__('filament.pages.manage_branding.font_size_key'))
                            ->valueLabel(__('filament.pages.manage_branding.font_size_value'))
                            ->helperText(__('filament.pages.manage_branding.font_sizes_helper'))
                            ->keyPlaceholder('base')
                            ->valuePlaceholder('1rem')
                            ->default([
                                'base' => '1rem',
                                'small' => '0.875rem',
                                'large' => '1.125rem',
                                'h1' => '3rem',
                                'h2' => '2.25rem',
                                'h3' => '1.875rem',
                                'h4' => '1.5rem',
                                'h5' => '1.25rem',
                                'h6' => '1.125rem',
                            ])
                            ->nullable(),
                    ]),

                Forms\Components\Section::make(__('filament.pages.manage_branding.background_colors_section'))
                    ->schema([
                        ColorPicker::make('background_color')
                            ->label(__('filament.pages.manage_branding.background_color'))
                            ->helperText(__('filament.pages.manage_branding.background_color_helper'))
                            ->nullable(),

                        ColorPicker::make('card_background_color')
                            ->label(__('filament.pages.manage_branding.card_background_color'))
                            ->default('#FFFFFF')
                            ->nullable(),

                        ColorPicker::make('hero_background_color')
                            ->label(__('filament.pages.manage_branding.hero_background_color'))
                            ->default('#111827')
                            ->nullable(),

                        ColorPicker::make('overlay_background_color')
                            ->label(__('filament.pages.manage_branding.overlay_background_color'))
                            ->rgba()
                            ->default('rgba(0,0,0,0.4)')
                            ->nullable(),

                        ColorPicker::make('header_background_color')
                            ->label(__('filament.pages.manage_branding.header_background_color'))
                            ->default('#FFFFFF')
                            ->nullable(),

                        ColorPicker::make('footer_background_color')
                            ->label(__('filament.pages.manage_branding.footer_background_color'))
                            ->default('#E5E7EB')
                            ->nullable(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('filament.pages.manage_branding.text_colors_section'))
                    ->schema([
                        ColorPicker::make('text_primary_color')
                            ->label(__('filament.pages.manage_branding.text_primary_color'))
                            ->helperText(__('filament.pages.manage_branding.text_primary_color_helper'))
                            ->nullable(),

                        ColorPicker::make('text_secondary_color')
                            ->label(__('filament.pages.manage_branding.text_secondary_color'))
                            ->helperText(__('filament.pages.manage_branding.text_secondary_color_helper'))
                            ->nullable(),

                        ColorPicker::make('text_inverse_color')
                            ->label(__('filament.pages.manage_branding.text_inverse_color'))
                            ->helperText(__('filament.pages.manage_branding.text_inverse_color_helper'))
                            ->nullable(),

                        ColorPicker::make('link_color')
                            ->label(__('filament.pages.manage_branding.link_color'))
                            ->helperText(__('filament.pages.manage_branding.link_color_helper'))
                            ->nullable(),

                        ColorPicker::make('link_hover_color')
                            ->label(__('filament.pages.manage_branding.link_hover_color'))
                            ->nullable(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('filament.pages.manage_branding.navigation_colors_section'))
                    ->schema([
                        ColorPicker::make('nav_text_color')
                            ->label(__('filament.pages.manage_branding.nav_text_color'))
                            ->default('#374151')
                            ->helperText(__('filament.pages.manage_branding.nav_text_color_helper'))
                            ->nullable(),

                        ColorPicker::make('nav_text_color_inactive')
                            ->label(__('filament.pages.manage_branding.nav_text_color_inactive'))
                            ->default('#9CA3AF')
                            ->helperText(__('filament.pages.manage_branding.nav_text_color_inactive_helper'))
                            ->nullable(),

                        ColorPicker::make('nav_hover_color')
                            ->label(__('filament.pages.manage_branding.nav_hover_color'))
                            ->default('#FCA5A5')
                            ->helperText(__('filament.pages.manage_branding.nav_hover_color_helper'))
                            ->nullable(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make(__('filament.pages.manage_branding.border_shadow_colors_section'))
                    ->schema([
                        ColorPicker::make('border_color')
                            ->label(__('filament.pages.manage_branding.border_color'))
                            ->helperText(__('filament.pages.manage_branding.border_color_helper'))
                            ->nullable(),

                        ColorPicker::make('divider_color')
                            ->label(__('filament.pages.manage_branding.divider_color'))
                            ->helperText(__('filament.pages.manage_branding.divider_color_helper'))
                            ->nullable(),

                        ColorPicker::make('shadow_color')
                            ->label(__('filament.pages.manage_branding.shadow_color'))
                            ->default('#000000')
                            ->helperText(__('filament.pages.manage_branding.shadow_color_helper'))
                            ->nullable(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make(__('filament.pages.manage_branding.slider_colors_section'))
                    ->schema([
                        ColorPicker::make('slider_colors.rail')
                            ->label(__('filament.pages.manage_branding.slider_rail_color'))
                            ->required(),

                        ColorPicker::make('slider_colors.handle')
                            ->label(__('filament.pages.manage_branding.slider_handle_color'))
                            ->required(),

                        ColorPicker::make('slider_colors.handleBorder')
                            ->label(__('filament.pages.manage_branding.slider_handle_border_color'))
                            ->required(),
                    ])
                    ->columns(3),
            ]);
    }
}