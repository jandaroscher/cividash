<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FooterNavigationResource\Pages;
use App\Models\FooterNavigation;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;

class FooterNavigationResource extends Resource
{
    use Translatable;

    protected static ?string $model = FooterNavigation::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Footer';

    protected static ?string $navigationGroup = 'Einstellungen';

    protected static ?int $navigationSort = 23;

    /**
     * Declare which model attributes are translatable for this resource.
     *
     * @return string[] The list of attribute names that should be translated.
     */
    public static function getTranslatableAttributes(): array
    {
        return ['footer_navigation_items', 'social_links', 'copyright_text'];
    }

    /**
     * Build the form schema used to create and edit footer navigation settings.
     *
     * Configures fields for footer navigation items, layout selection (with conditional
     * columns), social links, copyright text, and related controls.
     *
     * @param Form $form The form instance to configure.
     * @return Form The configured form containing the footer navigation schema.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Repeater::make('footer_navigation_items')
                    ->label(__('filament.pages.manage_footer.footer_navigation_items'))
                    ->schema(static::getNavigationItemSchemaForForm())
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(function (array $state, $livewire): ?string {
                        $locale = $livewire->activeLocale ?? app()->getLocale();
                        return is_array($state['label'] ?? null) 
                            ? ($state['label'][$locale] ?? $state['label']['de'] ?? '') 
                            : ($state['label'] ?? null);
                    })
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
                            ->disk('public')
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

    /**
     * Provide the route mappings for this resource's pages.
     *
     * @return array<string, mixed> An associative array mapping page identifiers (e.g. 'index', 'edit') to their route definitions.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFooterNavigations::route('/'),
            'edit' => Pages\EditFooterNavigation::route('/{record}/edit'),
        ];
    }

    /**
     * Builds the Filament form schema for a single footer navigation item.
     *
     * The returned schema contains the `type`, `page_id`, `label`, and `url` form components with locale-aware page titles,
     * conditional visibility/validation based on `type`, and automatic population of `label` and `url` when a page is selected.
     *
     * @return array The array of form components comprising the navigation item schema.
     */
    protected static function getNavigationItemSchemaForForm(): array
    {
        return [
            Select::make('type')
                ->label(__('filament.pages.manage_header.item_type'))
                ->options([
                    'page' => __('filament.pages.manage_header.type_page'),
                    'manual' => __('filament.pages.manage_header.type_manual'),
                ])
                ->default('page')
                ->required()
                ->reactive(),

            Select::make('page_id')
                ->label(__('filament.pages.manage_header.page'))
                ->options(function ($livewire) {
                    $locale = $livewire->activeLocale ?? app()->getLocale();
                    
                    return \App\Models\Page::query()
                        ->get()
                        ->mapWithKeys(function ($page) use ($locale) {
                            $title = $page->getTranslation('title', $locale, false) 
                                ?: $page->getTranslation('title', 'de', false) 
                                ?: 'Untitled';
                            return [$page->id => $title];
                        })
                        ->toArray();
                })
                ->searchable()
                ->required(fn ($get) => $get('type') === 'page')
                ->visible(fn ($get) => $get('type') === 'page')
                ->reactive()
                ->afterStateUpdated(function ($state, $set, $get, $livewire) {
                    if ($state && $get('type') === 'page') {
                        $page = \App\Models\Page::find($state);
                        if ($page) {
                            $locale = $livewire->activeLocale ?? app()->getLocale();
                            
                            $title = $page->getTranslation('title', $locale, false) 
                                ?: $page->getTranslation('title', 'de', false) 
                                ?: 'Untitled';
                            $url = $page->getUrl(['locale' => $locale]);
                            
                            $set('label', $title);
                            $set('url', $url);
                        }
                    }
                }),

            TextInput::make('label')
                ->label(__('filament.pages.manage_header.label'))
                ->required(fn ($get) => $get('type') === 'manual')
                ->visible(fn ($get) => $get('type') === 'manual')
                ->reactive(),

            TextInput::make('url')
                ->label(__('filament.pages.manage_header.url'))
                ->url()
                ->required(fn ($get) => $get('type') === 'manual')
                ->visible(fn ($get) => $get('type') === 'manual')
                ->reactive(),
        ];
    }
}