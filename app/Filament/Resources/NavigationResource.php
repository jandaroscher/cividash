<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasNavigationItemSchema;
use App\Filament\Resources\NavigationResource\Pages;
use App\Models\Navigation;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;

class NavigationResource extends Resource
{
    use Translatable;
    use HasNavigationItemSchema;

    protected static ?string $model = Navigation::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationLabel = 'Header / Navigation';

    protected static ?string $navigationGroup = 'Einstellungen';

    protected static ?int $navigationSort = 22;

    /**
     * Provide the model attribute names that should be treated as translatable.
     *
     * @return string[] Attribute names to translate; currently `['navigation_items']`.
     */
    public static function getTranslatableAttributes(): array
    {
        return ['navigation_items'];
    }

    /**
     * Builds the form schema used to manage the navigation resource, including a locale-aware
     * hierarchical `navigation_items` repeater and toggles for language switcher and dropdown behavior.
     *
     * The schema configures:
     * - A top-level `navigation_items` repeater with reorderable, collapsible items and locale-aware item labels.
     * - A nested `children` repeater (visible when dropdowns are enabled) that uses the same item schema.
     * - Toggles `show_language_switcher` (default true) and `dropdown_enabled` (default false, reactive).
     *
     * @param \Filament\Forms\Form $form The base form instance to configure.
     * @return \Filament\Forms\Form The provided form instance populated with the navigation schema.
     */
    public static function form(Form $form): Form
    {
        $navigationItemSchema = static::getNavigationItemSchemaForForm();
        
        return $form
            ->schema([
                Repeater::make('navigation_items')
                    ->label(__('filament.pages.manage_header.navigation_items'))
                    ->schema([
                        ...$navigationItemSchema,

                        Repeater::make('children')
                            ->label(__('filament.pages.manage_header.children'))
                            ->schema($navigationItemSchema)
                            ->visible(fn ($get) => $get('../../dropdown_enabled') === true)
                            ->collapsible()
                            ->itemLabel(function (array $state, $livewire): ?string {
                                $locale = $livewire->activeLocale ?? app()->getLocale();
                                return is_array($state['label'] ?? null) 
                                    ? ($state['label'][$locale] ?? $state['label']['de'] ?? '') 
                                    : ($state['label'] ?? null);
                            })
                            ->reorderable(),
                    ])
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(function (array $state, $livewire): ?string {
                        $locale = $livewire->activeLocale ?? app()->getLocale();
                        return is_array($state['label'] ?? null) 
                            ? ($state['label'][$locale] ?? $state['label']['de'] ?? '') 
                            : ($state['label'] ?? null);
                    })
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

    /**
     * Define the resource's available pages and their routes for Filament.
     *
     * @return array<string, mixed> Map of page identifiers (e.g., 'index', 'edit') to their route definitions.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNavigations::route('/'),
            'edit' => Pages\EditNavigation::route('/{record}/edit'),
        ];
    }

    /**
     * Build the form component schema for a single navigation item.
     *
     * The returned schema includes fields for item type, linked page selection, label, and URL.
     * When available, Livewire's `activeLocale` is used to localize page titles and to populate the label and URL after selecting a page.
     *
     * @return array<int, \Filament\Forms\Components\Component> An array of Filament form components that make up a navigation item.
     */
    protected static function getNavigationItemSchemaForForm(): array
    {
        return [
            \Filament\Forms\Components\Select::make('type')
                ->label(__('filament.pages.manage_header.item_type'))
                ->options([
                    'page' => __('filament.pages.manage_header.type_page'),
                    'manual' => __('filament.pages.manage_header.type_manual'),
                ])
                ->default('page')
                ->required()
                ->reactive(),

            \Filament\Forms\Components\Select::make('page_id')
                ->label(__('filament.pages.manage_header.page'))
                ->options(function ($livewire) {
                    $locale = $livewire->activeLocale ?? app()->getLocale();
                    
                    return \App\Models\Page::query()
                        ->where('is_public', true)
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
                            
                            // Get current values to preserve other locales
                            $currentLabel = $get('label') ?? [];
                            $currentUrl = $get('url') ?? [];
                            
                            // Set for current locale only
                            if (is_array($currentLabel)) {
                                $currentLabel[$locale] = $title;
                                $set('label', $currentLabel);
                            } else {
                                $set('label', [$locale => $title]);
                            }
                            
                            if (is_array($currentUrl)) {
                                $currentUrl[$locale] = $url;
                                $set('url', $currentUrl);
                            } else {
                                $set('url', [$locale => $url]);
                            }
                        }
                    }
                }),

            \Filament\Forms\Components\TextInput::make('label')
                ->label(__('filament.pages.manage_header.label'))
                ->required(fn ($get) => $get('type') === 'manual')
                ->visible(fn ($get) => $get('type') === 'manual')
                ->reactive(),

            \Filament\Forms\Components\TextInput::make('url')
                ->label(__('filament.pages.manage_header.url'))
                ->url()
                ->required(fn ($get) => $get('type') === 'manual')
                ->visible(fn ($get) => $get('type') === 'manual')
                ->reactive(),
        ];
    }
}