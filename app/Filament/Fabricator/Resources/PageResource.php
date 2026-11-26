<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Fabricator\Resources;

use App\Filament\Concerns\HasBlockActiveToggleAction;
use App\Filament\Concerns\HasSortableTranslations;
use App\Filament\Concerns\HasTranslatableRecordTitle;
use App\Filament\Fabricator\Resources\PageResource\Pages;
use App\Filament\Fabricator\Resources\PageResource\Pages\CreatePage;
use App\Filament\Fabricator\Resources\PageResource\Pages\EditPage;
use App\Filament\Fabricator\Resources\PageResource\Pages\ListPages;
use App\Filament\Support\MediaPicker;
use App\Models\Page;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Z3d0X\FilamentFabricator\Forms\Components\PageBuilder;
use Z3d0X\FilamentFabricator\Resources\PageResource as FabricatorPageResource;

class PageResource extends FabricatorPageResource
{
    use HasBlockActiveToggleAction;
    use HasSortableTranslations;
    use HasTranslatableRecordTitle;
    use Translatable;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.page.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.content');
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
     * Replaces the `page_url` entry inside sidebar sections with a `TextEntry` that:
     * - is labeled with the translated URL label,
     * - is visible only when Fabricator routing is enabled and a record exists,
     * - displays `'-'` when no record is present,
     * - otherwise returns the record URL for the active locale (from the Livewire component's `activeLocale` property or `app()->getLocale()`).
     *
     * @param  Schema  $schema  The base form to modify.
     * @return Schema The modified form instance.
     */
    public static function form(Schema $schema): Schema
    {
        // Get the base form structure from parent
        $schema = parent::form($schema);

        // We need to customize the sidebar schema to make the URL field locale-aware
        // The parent uses a Section with a page_url TextEntry, we need to override it

        // Get all components
        $components = $schema->getComponents();

        // Find the right sidebar column and replace the page_url entry
        foreach ($components as $columnKey => $column) {
            if (method_exists($column, 'getDefaultChildComponents')) {
                $childComponents = $column->getDefaultChildComponents();

                foreach ($childComponents as $sectionKey => $section) {
                    if ($section instanceof Section && method_exists($section, 'getDefaultChildComponents')) {
                        $sectionChildren = $section->getDefaultChildComponents();

                        foreach ($sectionChildren as $placeholderKey => $placeholder) {
                            if ($placeholder instanceof TextEntry && $placeholder->getName() === 'page_url') {
                                // Replace with our custom locale-aware version
                                $sectionChildren[$placeholderKey] = TextEntry::make('page_url')
                                    ->label(__('filament.resources.page.url_preview'))
                                    ->visible(fn (?Page $record) => config('filament-fabricator.routing.enabled') && filled($record))
                                    ->state(function (?Page $record, $livewire) {
                                        if (! $record) {
                                            return '-';
                                        }

                                        // Get the active locale from the Livewire component
                                        $activeLocale = property_exists($livewire, 'activeLocale')
                                            ? $livewire->activeLocale
                                            : app()->getLocale();

                                        // Generate URL with the correct locale
                                        return $record->getUrl(['locale' => $activeLocale]);
                                    });

                                // Remove the vendor's title field from the sidebar; it
                                // now lives in the dedicated full-width top Section below.
                                $sectionChildren = array_values(array_filter(
                                    $sectionChildren,
                                    fn ($component) => ! (method_exists($component, 'getName') && $component->getName() === 'title')
                                ));

                                $existingNames = collect($sectionChildren)
                                    ->filter(fn ($component) => method_exists($component, 'getName'))
                                    ->map(fn ($component) => $component->getName())
                                    ->filter()
                                    ->all();

                                $additionalComponents = [
                                    TextInput::make('slug')
                                        ->label(__('filament.resources.page.slug'))
                                        // The landing page always lives at the root slug "/", forced by
                                        // Page::saving(). Disabling + not dehydrating the field for that
                                        // layout keeps the value out of the submitted form data, so the
                                        // model hook stays the single source of truth and the regex/
                                        // required rules below never reject the forced "/".
                                        ->disabled(fn (Get $get) => $get('layout') === 'landingpage')
                                        ->dehydrated(fn (Get $get) => $get('layout') !== 'landingpage')
                                        ->required(fn (Get $get) => $get('layout') !== 'landingpage')
                                        ->maxLength(255)
                                        ->helperText(fn (Get $get) => $get('layout') === 'landingpage'
                                            ? __('filament.resources.page.slug_landingpage_helper')
                                            : null)
                                        ->rule(
                                            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                                            fn (Get $get) => $get('layout') !== 'landingpage',
                                        )
                                        ->validationMessages([
                                            'regex' => __('filament.resources.page.slug_validation'),
                                        ])
                                        ->rule(function (Get $get, ?Page $record, $livewire) {
                                            return function (string $attribute, $value, Closure $fail) use ($get, $record, $livewire) {
                                                if (blank($value) || $get('layout') === 'landingpage') {
                                                    return;
                                                }

                                                $locale = property_exists($livewire, 'activeLocale')
                                                    ? $livewire->activeLocale
                                                    : app()->getLocale();

                                                // Mirror the config-driven locale list used by
                                                // PageObserver::invalidateUrlCache(); hardcoding ['de','en']
                                                // would skip uniqueness checks for any newly configured locale.
                                                if (! in_array($locale, config('app.available_locales', ['de', 'en']), true)) {
                                                    return;
                                                }

                                                // slug is stored as a translatable JSON column, so a plain
                                                // ->unique() rule can't compare against it directly (and
                                                // would error on PostgreSQL, where the column is jsonb).
                                                // Use the app-wide whereTranslation() macro instead, scoped
                                                // to the same parent (siblings must have distinct slugs;
                                                // the tenant scope is already applied by BelongsToTenant).
                                                $query = Page::whereTranslation('slug', $locale, $value)
                                                    ->where('parent_id', $get('parent_id'));

                                                if ($record) {
                                                    $query->whereKeyNot($record->getKey());
                                                }

                                                if ($query->exists()) {
                                                    $fail(__('filament.resources.page.slug_unique'));
                                                }
                                            };
                                        }),
                                    Select::make('layout')
                                        ->label(__('filament.resources.page.layout'))
                                        ->options(static::getLayoutOptions())
                                        // A tenant has exactly one landing page (root slug "/").
                                        // Default a brand-new page to "landingpage" only while none
                                        // exists yet; once one is present, new pages should default to
                                        // "subpage" so they don't accidentally overwrite the root slug.
                                        ->default(fn () => static::getDefaultLayoutForNewPage())
                                        // Reactive so the slug field toggles its disabled/helper/required
                                        // state immediately when the editor switches the layout.
                                        ->live()
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
                                    MediaPicker::make('meta_image')
                                        ->label(__('filament.resources.page.meta_image'))
                                        ->acceptedFileTypes(['image/*'])
                                        ->maxSize(5120),
                                ];

                                foreach ($additionalComponents as $component) {
                                    $name = method_exists($component, 'getName') ? $component->getName() : null;

                                    if ($name && in_array($name, $existingNames, true)) {
                                        // Replace the vendor field of the same name with our
                                        // customized version (e.g. locale-aware parent_id options,
                                        // our slug regex/uniqueness validation, our labels). Without
                                        // this, the vendor's original field silently stays active and
                                        // all of our overrides below become dead code.
                                        foreach ($sectionChildren as $k => $existing) {
                                            if (method_exists($existing, 'getName') && $existing->getName() === $name) {
                                                $sectionChildren[$k] = $component;
                                                break;
                                            }
                                        }

                                        continue;
                                    }

                                    $sectionChildren[] = $component;
                                    $existingNames[] = $name;
                                }

                                // Update the section with modified children and add heading
                                $section->heading(__('filament.resources.page.sidebar_title'));
                                $section->schema($sectionChildren);
                            }
                        }
                    }
                }
            }
        }

        // Wrap the PageBuilder in a Section for a white card background (matching TileResource)
        foreach ($components as $column) {
            if (method_exists($column, 'getDefaultChildComponents')) {
                $children = $column->getDefaultChildComponents();

                foreach ($children as $key => $child) {
                    if ($child instanceof PageBuilder) {
                        $children[$key] = Section::make()
                            ->schema([$child]);
                        $column->schema($children);
                        break 2;
                    }
                }
            }
        }

        static::applyBlockToggleActionToComponents($components);

        // Move the title into a dedicated full-width Section at the very top of the
        // form, above the existing two-column content/properties layout. The slug and
        // remaining page properties stay in the right sidebar column (handled above).
        $titleSection = Section::make()
            ->columnSpanFull()
            ->schema([
                TextInput::make('title')
                    ->label(__('filament.resources.page.title'))
                    ->helperText(__('filament.resources.page.title_helper'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    // Preserve the vendor auto-slug behaviour: derive the slug from the
                    // title for new records until the user edits the slug manually.
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?Page $record): void {
                        if (! $get('is_slug_changed_manually') && filled($state) && blank($record)) {
                            $set('slug', Str::slug($state, language: config('app.locale', 'en')));
                        }
                    }),
            ]);

        $schema->components([$titleSection, ...$components]);

        return $schema;
    }

    /**
     * Configure the resource index table with its columns, filters, row actions, and bulk actions.
     *
     * @param  Table  $table  The base table instance to configure.
     * @return Table The configured table instance.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament.resources.page.title'))
                    ->formatStateUsing(function ($state, Page $record, $livewire) {
                        $locale = $livewire->activeLocale ?? app()->getLocale();

                        return $record->getTranslation('title', $locale, false)
                            ?: $record->getTranslation('title', 'de', false)
                            ?: $state;
                    })
                    ->searchable(query: static::getSearchableTranslationClosure('title'))
                    ->sortable(query: function (Builder $query, string $direction, $livewire) {
                        $locale = $livewire->activeLocale ?? app()->getLocale();
                        $expression = static::getSortableTranslationExpression('title', $locale);

                        $query->orderByRaw("{$expression} {$direction}");
                    }),
                TextColumn::make('slug')
                    ->label(__('filament.resources.page.slug'))
                    ->formatStateUsing(function ($state, Page $record, $livewire) {
                        $locale = $livewire->activeLocale ?? app()->getLocale();

                        return $record->getTranslation('slug', $locale, false)
                            ?: $record->getTranslation('slug', 'de', false)
                            ?: $state;
                    })
                    ->searchable(query: static::getSearchableTranslationClosure('slug'))
                    ->sortable(query: function (Builder $query, string $direction, $livewire) {
                        $locale = $livewire->activeLocale ?? app()->getLocale();
                        $expression = static::getSortableTranslationExpression('slug', $locale);

                        $query->orderByRaw("{$expression} {$direction}");
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('layout')
                    ->label(__('filament.resources.page.layout'))
                    ->sortable(),
                ToggleColumn::make('is_public')
                    ->label(__('filament.resources.page.is_public'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament.resources.page.created_at'))
                    ->dateTime(__('filament.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('filament.resources.page.updated_at'))
                    ->dateTime(__('filament.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('layout')
                    ->label(__('filament.resources.page.layout'))
                    ->options(static::getLayoutOptions()),
                TernaryFilter::make('is_public')
                    ->label(__('filament.resources.page.is_public')),
            ])
            ->recordUrl(fn (Page $record, $livewire) => static::getUrl('edit', [
                'record' => $record,
                'activeLocale' => $livewire->activeLocale ?? app()->getLocale(),
            ]))
            ->recordActions([
                EditAction::make()
                    ->url(fn (Page $record, $livewire) => static::getUrl('edit', [
                        'record' => $record,
                        'activeLocale' => $livewire->activeLocale ?? app()->getLocale(),
                    ])),
                Action::make('view_frontend')
                    ->label(__('filament.resources.page.actions.view_frontend'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('success')
                    ->url(function (Page $record, $livewire) {
                        $locale = $livewire->activeLocale ?? app()->getLocale();

                        return $record->getFrontendUrl(['locale' => $locale]);
                    })
                    ->openUrlInNewTab(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @param  array<Component>  $components
     */
    protected static function applyBlockToggleActionToComponents(array $components): void
    {
        foreach ($components as $component) {
            if ($component instanceof PageBuilder) {
                $component
                    ->addActionLabel(__('filament.actions.add_to_blocks'))
                    ->extraItemActions([
                        static::getBlockActiveToggleAction(),
                    ])
                    ->blockNumbers(false);

                continue;
            }

            if (method_exists($component, 'getDefaultChildComponents')) {
                static::applyBlockToggleActionToComponents($component->getDefaultChildComponents());
            }
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
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

    /**
     * Default layout for a newly created page.
     *
     * A tenant has exactly one landing page (served at the root slug "/").
     * While no landing page exists yet, a new page defaults to "landingpage";
     * once one is present, new pages default to "subpage" so saving them does
     * not get forced onto the root slug by the Page model's saving hook.
     */
    protected static function getDefaultLayoutForNewPage(): ?string
    {
        $options = static::getLayoutOptions();

        $landingExists = array_key_exists('landingpage', $options)
            && Page::query()->where('layout', 'landingpage')->exists();

        if ($landingExists) {
            return array_key_exists('subpage', $options) ? 'subpage' : array_key_first($options);
        }

        return array_key_exists('landingpage', $options) ? 'landingpage' : array_key_first($options);
    }
}
