<?php

namespace App\Filament\Fabricator\Resources;

use App\Filament\Concerns\HasBlockActiveToggleAction;
use App\Filament\Concerns\HasSortableTranslations;
use App\Filament\Fabricator\Resources\PageResource\Pages;
use App\Models\Page;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Z3d0X\FilamentFabricator\Forms\Components\PageBuilder;
use Z3d0X\FilamentFabricator\Resources\PageResource as FabricatorPageResource;

class PageResource extends FabricatorPageResource
{
    use HasBlockActiveToggleAction;
    use HasSortableTranslations;
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
     * Replaces any `Placeholder` named `page_url` inside sidebar sections with a `Placeholder` that:
     * - is labeled with the translated URL label,
     * - is visible only when Fabricator routing is enabled and a record exists,
     * - displays `'-'` when no record is present,
     * - otherwise returns the record URL for the active locale (from the Livewire component's `activeLocale` property or `app()->getLocale()`).
     *
     * @param  \Filament\Forms\Form  $form  The base form to modify.
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
                                        ->required()
                                        ->maxLength(255)
                                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                        ->validationMessages([
                                            'regex' => __('filament.resources.page.slug_validation'),
                                        ]),
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
                                        // Replace vendor parent_id with our locale-aware version
                                        if ($name === 'parent_id') {
                                            foreach ($sectionChildren as $k => $existing) {
                                                if (method_exists($existing, 'getName') && $existing->getName() === 'parent_id') {
                                                    $sectionChildren[$k] = $component;
                                                    break;
                                                }
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
            if (method_exists($column, 'getChildComponents')) {
                $children = $column->getChildComponents();

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

        $form->schema([$titleSection, ...$components]);

        return $form;
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
                Tables\Columns\TextColumn::make('title')
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
                Tables\Columns\TextColumn::make('slug')
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
                Tables\Columns\TextColumn::make('layout')
                    ->label(__('filament.resources.page.layout'))
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_public')
                    ->label(__('filament.resources.page.is_public'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.resources.page.created_at'))
                    ->dateTime(__('filament.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.resources.page.updated_at'))
                    ->dateTime(__('filament.date_time_format'))
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
            ->recordUrl(fn (Page $record, $livewire) => static::getUrl('edit', [
                'record' => $record,
                'activeLocale' => $livewire->activeLocale ?? app()->getLocale(),
            ]))
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(fn (Page $record, $livewire) => static::getUrl('edit', [
                        'record' => $record,
                        'activeLocale' => $livewire->activeLocale ?? app()->getLocale(),
                    ])),
                Tables\Actions\Action::make('view_frontend')
                    ->label(__('filament.resources.page.actions.view_frontend'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('success')
                    ->url(function (Page $record, $livewire) {
                        $locale = $livewire->activeLocale ?? app()->getLocale();

                        return $record->getFrontendUrl(['locale' => $locale]);
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

    /**
     * @param  array<\Filament\Forms\Components\Component>  $components
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

            if (method_exists($component, 'getChildComponents')) {
                static::applyBlockToggleActionToComponents($component->getChildComponents());
            }
        }
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
