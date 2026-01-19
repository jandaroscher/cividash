<?php

namespace App\Filament\Fabricator\Resources;

use App\Filament\Fabricator\Resources\PageResource\Pages;
use App\Models\Page;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;
use Z3d0X\FilamentFabricator\Resources\PageResource as FabricatorPageResource;
use Z3d0X\FilamentFabricator\Enums\ResourceSchemaSlot;
use Z3d0X\FilamentFabricator\Forms\Components\PageBuilder;

class PageResource extends FabricatorPageResource
{
    use Translatable;

    protected static ?string $navigationGroup = 'Inhalte';
    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.page.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.page.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.page.plural_model_label');
    }

    /**
     * Provides the label displayed for this resource in the Filament navigation.
     *
     * @return string The navigation label "Seiten".
     */
    /**
     * Customize the parent form to replace the sidebar's `page_url` placeholder with a locale-aware placeholder.
     *
     * Replaces any `Placeholder` named `page_url` inside sidebar sections with a `Placeholder` that:
     * - is labeled with the translated URL label,
     * - is visible only when Fabricator routing is enabled and a record exists,
     * - displays `'-'` when no record is present,
     * - otherwise returns the record URL for the active locale (from the Livewire component's `activeLocale` property or `app()->getLocale()`).
     *
     * @param \Filament\Forms\Form $form The base form to modify.
     * @return \Filament\Forms\Form The modified form instance.
     */
    public static function form(Form $form): Form
    {
        // Get the base form structure from parent
        $form = parent::form($form);
        
        // We need to customize the sidebar schema to make the URL field locale-aware
        // The parent uses a Section with page_url Placeholder, we need to override it
        
        // Get all components
        $components = $form->getComponents();
        
        // Find the right sidebar column and replace the page_url Placeholder
        foreach ($components as $columnKey => $column) {
            if (method_exists($column, 'getChildComponents')) {
                $childComponents = $column->getChildComponents();
                
                foreach ($childComponents as $sectionKey => $section) {
                    if ($section instanceof Section && method_exists($section, 'getChildComponents')) {
                        $sectionChildren = $section->getChildComponents();
                        
                        foreach ($sectionChildren as $placeholderKey => $placeholder) {
                            if ($placeholder instanceof Placeholder && $placeholder->getName() === 'page_url') {
                                // Replace with our custom locale-aware version
                                $sectionChildren[$placeholderKey] = Placeholder::make('page_url')
                                    ->label(__('filament.resources.page.url_preview'))
                                    ->visible(fn (?Page $record) => config('filament-fabricator.routing.enabled') && filled($record))
                                    ->content(function (?Page $record, $livewire) {
                                        if (!$record) {
                                            return '-';
                                        }
                                        
                                        // Get the active locale from the Livewire component
                                        $activeLocale = property_exists($livewire, 'activeLocale') 
                                            ? $livewire->activeLocale 
                                            : app()->getLocale();
                                        
                                        // Generate URL with the correct locale
                                        return $record->getUrl(['locale' => $activeLocale]);
                                    });

                                $existingNames = collect($sectionChildren)
                                    ->filter(fn ($component) => method_exists($component, 'getName'))
                                    ->map(fn ($component) => $component->getName())
                                    ->filter()
                                    ->all();

                                $additionalComponents = [
                                    TextInput::make('title')
                                        ->label(__('filament.resources.page.title'))
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('slug')
                                        ->label(__('filament.resources.page.slug'))
                                        ->required()
                                        ->maxLength(255),
                                    Select::make('layout')
                                        ->label(__('filament.resources.page.layout'))
                                        ->options(static::getLayoutOptions())
                                        ->required(),
                                    Select::make('parent_id')
                                        ->label(__('filament.resources.page.parent'))
                                        ->options(function ($livewire, ?Page $record) {
                                            $locale = $livewire->activeLocale ?? app()->getLocale();

                                            return Page::query()
                                                ->when($record?->id, fn ($query) => $query->whereKeyNot($record->id))
                                                ->get()
                                                ->mapWithKeys(function (Page $page) use ($locale) {
                                                    $title = $page->getTranslation('title', $locale, false)
                                                        ?: $page->getTranslation('title', 'de', false)
                                                        ?: 'Untitled';

                                                    return [$page->id => $title];
                                                })
                                                ->toArray();
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->nullable(),
                                    Toggle::make('is_public')
                                        ->label(__('filament.resources.page.is_public'))
                                        ->default(true),
                                    TextInput::make('meta_title')
                                        ->label(__('filament.resources.page.meta_title'))
                                        ->maxLength(255),
                                    Textarea::make('meta_description')
                                        ->label(__('filament.resources.page.meta_description'))
                                        ->rows(3),
                                    FileUpload::make('meta_image')
                                        ->label(__('filament.resources.page.meta_image'))
                                        ->image()
                                        ->disk('public')
                                        ->directory('pages/seo')
                                        ->preserveFilenames(),
                                ];

                                foreach ($additionalComponents as $component) {
                                    $name = method_exists($component, 'getName') ? $component->getName() : null;

                                    if ($name && in_array($name, $existingNames, true)) {
                                        continue;
                                    }

                                    $sectionChildren[] = $component;
                                    $existingNames[] = $name;
                                }

                                // Update the section with modified children
                                $section->schema($sectionChildren);
                            }
                        }
                    }
                }
            }
        }
        
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('filament.resources.page.title'))
                    ->formatStateUsing(function ($state, Page $record) {
                        return $record->getTranslation('title', app()->getLocale(), false)
                            ?: $record->getTranslation('title', 'de', false)
                            ?: $state;
                    })
                    ->searchable()
                    ->sortable(query: function (Builder $query, string $direction) {
                        $expression = static::getSortableTranslationExpression('title', app()->getLocale());

                        $query->orderByRaw("{$expression} {$direction}");
                    }),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('filament.resources.page.slug'))
                    ->formatStateUsing(function ($state, Page $record) {
                        return $record->getTranslation('slug', app()->getLocale(), false)
                            ?: $record->getTranslation('slug', 'de', false)
                            ?: $state;
                    })
                    ->searchable()
                    ->sortable(query: function (Builder $query, string $direction) {
                        $expression = static::getSortableTranslationExpression('slug', app()->getLocale());

                        $query->orderByRaw("{$expression} {$direction}");
                    }),
                Tables\Columns\TextColumn::make('layout')
                    ->label(__('filament.resources.page.layout'))
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_public')
                    ->label(__('filament.resources.page.is_public'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.resources.page.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.resources.page.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('layout')
                    ->label(__('filament.resources.page.layout'))
                    ->options(static::getLayoutOptions()),
                Tables\Filters\TernaryFilter::make('is_public')
                    ->label(__('filament.resources.page.is_public')),
            ])
            ->recordUrl(fn (Page $record) => static::getUrl('edit', ['record' => $record]))
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_frontend')
                    ->label(__('filament.resources.page.actions.view_frontend'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('success')
                    ->url(function (Page $record, $livewire) {
                        $locale = $livewire->activeLocale ?? app()->getLocale();
                        return $record->getUrl(['locale' => $locale]);
                    })
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function getSortableTranslationExpression(string $column, ?string $locale = null): string
    {
        $locale = static::normalizeSortLocale($locale);
        $localePath = '$."' . $locale . '"';
        $fallbackPath = '$."de"';

        return sprintf(
            "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(%s, '%s')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(%s, '%s')), ''))",
            $column,
            $localePath,
            $column,
            $fallbackPath
        );
    }

    protected static function normalizeSortLocale(?string $locale = null): string
    {
        $allowedLocales = config('app.available_locales', ['de', 'en']);

        if (! is_array($allowedLocales) || $allowedLocales === []) {
            $allowedLocales = ['de', 'en'];
        }

        $locale = $locale ?: 'de';

        return in_array($locale, $allowedLocales, true) ? $locale : 'de';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }

    /**
     * Resolve layout options from the configured Fabricator layouts.
     *
     * @return array<string, string>
     */
    protected static function getLayoutOptions(): array
    {
        $layouts = config('filament-fabricator.layouts.register', []);
        $options = [];

        foreach ($layouts as $layout) {
            if (! class_exists($layout)) {
                continue;
            }

            $name = method_exists($layout, 'getName') ? $layout::getName() : $layout;
            $label = method_exists($layout, 'getLabel') ? $layout::getLabel() : $name;

            $options[$name] = $label;
        }

        if (! array_key_exists('default', $options)) {
            $options['default'] = 'default';
        }

        return $options;
    }
}
