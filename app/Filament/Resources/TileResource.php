<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasBlockActiveToggleAction;
use App\Filament\Concerns\HasSortableTranslations;
use App\Filament\Fabricator\PageBlocks\FAQBlock;
use App\Filament\Fabricator\PageBlocks\IntroTextBlock;
use App\Filament\Fabricator\PageBlocks\SliderBlock;
use App\Filament\Fabricator\PageBlocks\TextImageBlock;
use App\Filament\Resources\TileResource\Pages;
use App\Filament\Support\RichEditorConfig;
use App\Models\CategoryGroup;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tile;
use App\Models\TileYear;
use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

class TileResource extends Resource
{
    use HasBlockActiveToggleAction;
    use HasSortableTranslations;
    use Translatable;

    protected const CATEGORY_GROUP_FIELD_PREFIX = 'category_group_';

    protected static ?string $model = Tile::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 2;

    /**
     * Get the navigation label for the Tile resource (localized).
     *
     * @return string The localized navigation label.
     */
    public static function getNavigationLabel(): string
    {
        return __('filament.resources.tile.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.content');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.tile.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.tile.plural_model_label');
    }

    /**
     * Builds the form schema for the Tile resource, composed of three tabs: Tile, Background Page, and Metrics.
     *
     * @param  Form  $form  The form instance to configure.
     * @return Form The configured form instance.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Grid::make()
                    ->schema([
                        Tabs::make(__('filament.tabs.contents'))
                            ->columnSpanFull()
                            ->tabs([
                                // Tab 1: tile
                                Tabs\Tab::make(__('filament.tabs.tile'))
                                    ->schema([
                                        ...static::getCategoryGroupFields(),
                                        RichEditorConfig::make('description')
                                            ->label(__('filament.resources.tile.description')),
                                        FileUpload::make('icon')
                                            ->label(__('filament.resources.tile.icon'))
                                            ->disk('public')
                                            ->directory('tiles')
                                            ->preserveFilenames()
                                            ->acceptedFileTypes(['image/*', 'application/json', 'application/zip+dotlottie'])
                                            ->required(false),
                                        ViewField::make('icon_preview')
                                            ->view('filament.forms.components.lottie-preview')
                                            ->dehydrated(false)
                                            ->afterStateHydrated(fn ($component, $record) => $component->state($record?->icon)),
                                        TextInput::make('position')
                                            ->label(__('filament.resources.tile.position'))
                                            ->numeric()
                                            ->default(0),
                                    ]),

                                // Tab 2: background page
                                Tabs\Tab::make(__('filament.tabs.background_page'))
                                    ->schema([
                                        Builder::make('background_blocks')
                                            ->label(__('filament.resources.tile.background_blocks'))
                                            ->addActionLabel(__('filament.actions.add_to_background_blocks'))
                                            ->blocks(static::getBackgroundBlockSchemas())
                                            ->extraItemActions([
                                                static::getBlockActiveToggleAction(),
                                            ])
                                            ->collapsible()
                                            ->collapsed(false),
                                    ]),

                                // Tab 3: metrics
                                Tabs\Tab::make(__('filament.tabs.metrics'))
                                    ->schema([
                                        Repeater::make('metricDefinitions')
                                            ->relationship('metricDefinitions')
                                            ->orderColumn('sort_order')
                                            ->label(__('filament.resources.tile.metrics_label'))
                                            ->addActionLabel(__('filament.actions.add_to_metrics'))
                                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                            ->extraItemActions([
                                                static::getBlockActiveToggleAction(),
                                            ])
                                            ->schema([
                                                TextInput::make('metric_key')
                                                    ->label(__('filament.resources.tile.metric_key'))
                                                    ->helperText(__('filament.resources.tile.metric_key_helper'))
                                                    ->regex('/^[a-z][a-z0-9_-]*$/')
                                                    ->required()
                                                    ->disabled(fn (?MetricDefinition $record): bool => $record !== null)
                                                    ->unique(
                                                        table: MetricDefinition::class,
                                                        column: 'metric_key',
                                                        ignorable: fn (?MetricDefinition $record) => $record,
                                                        modifyRuleUsing: function ($rule, ?MetricDefinition $record, $livewire) {
                                                            $tileId = $record?->tile_id ?? $livewire->getRecord()?->id;

                                                            return $rule->where('tile_id', $tileId);
                                                        },
                                                    ),
                                                Hidden::make('is_active')
                                                    ->default(true)
                                                    ->afterStateHydrated(function (Hidden $component, $state): void {
                                                        if ($state === null) {
                                                            $component->state(true);
                                                        }
                                                    }),
                                                TextInput::make('label')
                                                    ->label(__('filament.resources.tile.label'))
                                                    ->required(),
                                                TextInput::make('unit')
                                                    ->label(__('filament.resources.tile.unit')),
                                                Select::make('indicator_type')
                                                    ->label(__('filament.resources.tile.indicator_type'))
                                                    ->options([
                                                        'small' => __('filament.resources.tile.indicator_type_small'),
                                                        'big' => __('filament.resources.tile.indicator_type_big'),
                                                    ])
                                                    ->default('small')
                                                    ->required(),
                                                FileUpload::make('icon')
                                                    ->label(__('filament.resources.tile.icon'))
                                                    ->disk('public')
                                                    ->directory('metrics')
                                                    ->image()
                                                    ->preserveFilenames()
                                                    ->required(false),
                                                Repeater::make('metricValues')
                                                    ->relationship('metricValues')
                                                    ->orderColumn('sort_order')
                                                    ->label(__('filament.resources.tile.metric_values'))
                                                    ->addActionLabel(__('filament.actions.add_to_metric_values'))
                                                    ->itemLabel(function (array $state, $record): ?string {
                                                        // Display the year as label for each metric value entry
                                                        // Prefer loaded relationship to avoid N+1 queries
                                                        if ($record && $record->relationLoaded('tileYear') && $record->tileYear) {
                                                            return (string) $record->tileYear->year;
                                                        }
                                                        // Fallback: load from database if relationship not loaded
                                                        if (isset($state['tile_year_id']) && is_numeric($state['tile_year_id'])) {
                                                            $tileYear = TileYear::find($state['tile_year_id']);

                                                            return $tileYear ? (string) $tileYear->year : (string) $state['tile_year_id'];
                                                        }

                                                        return null;
                                                    })
                                                    ->extraItemActions([
                                                        static::getBlockActiveToggleAction(),
                                                    ])
                                                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data, $record, $livewire): array {
                                                        $tile = static::resolveTileForMetricValue($record, $livewire);

                                                        return static::resolveMetricValueTileYearId($data, $tile);
                                                    })
                                                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data, $record, $livewire): array {
                                                        $tile = static::resolveTileForMetricValue($record, $livewire);

                                                        return static::resolveMetricValueTileYearId($data, $tile);
                                                    })
                                                    ->schema([
                                                        Hidden::make('is_active')
                                                            ->default(true)
                                                            ->afterStateHydrated(function (Hidden $component, $state): void {
                                                                if ($state === null) {
                                                                    $component->state(true);
                                                                }
                                                            }),
                                                        TextInput::make('tile_year_id')
                                                            ->label(__('filament.resources.tile.year'))
                                                            ->numeric()
                                                            ->rule('integer')
                                                            ->rule(function (Get $get) {
                                                                return function (string $attribute, $value, Closure $fail) use ($get) {
                                                                    if (! is_numeric($value)) {
                                                                        return;
                                                                    }
                                                                    $parent = $get('../../');
                                                                    $siblings = is_array($parent) ? ($parent['metricValues'] ?? []) : [];
                                                                    if (! is_array($siblings)) {
                                                                        return;
                                                                    }
                                                                    $count = collect($siblings)
                                                                        ->filter(fn ($item) => isset($item['tile_year_id']) && (int) $item['tile_year_id'] === (int) $value)
                                                                        ->count();
                                                                    if ($count > 1) {
                                                                        $fail(__('filament.resources.tile.duplicate_year'));
                                                                    }
                                                                };
                                                            })
                                                            ->afterStateHydrated(function (TextInput $component, $state, $record): void {
                                                                if ($record && $record->relationLoaded('tileYear') && $record->tileYear) {
                                                                    $component->state($record->tileYear->year);

                                                                    return;
                                                                }

                                                                if (is_numeric($state)) {
                                                                    $tileYear = TileYear::find($state);
                                                                    if ($tileYear) {
                                                                        $component->state($tileYear->year);
                                                                    }
                                                                }
                                                            })
                                                            ->dehydrateStateUsing(function ($state) {
                                                                $year = is_numeric($state) ? (int) $state : null;

                                                                return $year;
                                                            })
                                                            ->required(),
                                                        TextInput::make('value')
                                                            ->label(__('filament.resources.tile.value'))
                                                            ->numeric()
                                                            ->required(),
                                                    ])
                                                    ->collapsible(),
                                            ])
                                            ->collapsible(),
                                    ]),
                            ])
                            ->columnSpan(['lg' => 2]),
                        Section::make(__('filament.resources.tile.sidebar_title'))
                            ->schema([
                                Placeholder::make('tile_url')
                                    ->label(__('filament.resources.tile.url_preview'))
                                    ->visible(fn (?Tile $record) => filled($record))
                                    ->content(function (?Tile $record, $livewire) {
                                        if (! $record) {
                                            return '-';
                                        }

                                        $activeLocale = property_exists($livewire, 'activeLocale')
                                            ? $livewire->activeLocale
                                            : app()->getLocale();

                                        return $record->getUrl(['locale' => $activeLocale]);
                                    }),
                                TextInput::make('title')
                                    ->label(__('filament.resources.tile.title'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('slug')
                                    ->label(__('filament.resources.tile.slug'))
                                    ->maxLength(255)
                                    ->rule(function (TextInput $component) {
                                        return function (string $attribute, $value, \Closure $fail) use ($component): void {
                                            if (blank($value)) {
                                                return;
                                            }

                                            $livewire = $component->getLivewire();
                                            $activeLocale = property_exists($livewire, 'activeLocale')
                                                ? $livewire->activeLocale
                                                : app()->getLocale();
                                            $locale = static::normalizeSortLocale($activeLocale);
                                            $tenantId = auth()->user()?->tenant_id;

                                            $record = $component->getRecord();
                                            if (! $record && $livewire && method_exists($livewire, 'getRecord')) {
                                                $record = $livewire->getRecord();
                                            }

                                            $query = Tile::query();
                                            if ($tenantId) {
                                                $query->where('tenant_id', $tenantId);
                                            }
                                            if ($record) {
                                                $query->whereKeyNot($record->getKey());
                                            }

                                            $driver = $query->getConnection()->getDriverName();
                                            $localePath = '$."'.$locale.'"';

                                            if ($driver === 'sqlite') {
                                                $query->whereRaw('json_extract(slug, ?) = ?', [$localePath, $value]);
                                            } elseif (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
                                                $query->whereRaw('slug->> ? = ?', [$locale, $value]);
                                            } else {
                                                $query->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(slug, ?)) = ?', [$localePath, $value]);
                                            }

                                            if ($query->exists()) {
                                                $fail(__('validation.unique', ['attribute' => $attribute]));
                                            }
                                        };
                                    }),
                                Toggle::make('is_public')
                                    ->label(__('filament.resources.tile.is_public'))
                                    ->default(true),
                                TextInput::make('meta_title')
                                    ->label(__('filament.resources.tile.meta_title'))
                                    ->maxLength(255),
                                Textarea::make('meta_description')
                                    ->label(__('filament.resources.tile.meta_description'))
                                    ->rows(3),
                                FileUpload::make('meta_image')
                                    ->label(__('filament.resources.tile.meta_image'))
                                    ->image()
                                    ->disk('public')
                                    ->directory('tiles/seo')
                                    ->preserveFilenames(),
                            ])
                            ->columnSpan(['lg' => 1]),
                    ])
                    ->columns(['lg' => 3]),
            ]);
    }

    /**
     * Configure the Tile list table with columns, filters, actions, and bulk actions.
     *
     * Configures columns (localized title/slug with custom sorting, icon, position, visibility toggles, public toggle, timestamps),
     * filters (category relationship and public ternary), the record edit URL, row actions (edit, frontend view, delete),
     * and grouped bulk delete action.
     *
     * @param  \Filament\Tables\Table  $table  The table instance to configure.
     * @return \Filament\Tables\Table The configured table instance.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('filament.resources.tile.title'))
                    ->formatStateUsing(function ($state, Tile $record, $livewire) {
                        $locale = $livewire->activeLocale ?? app()->getLocale();

                        return $record->getTranslation('title', $locale, false)
                            ?: $record->getTranslation('title', 'de', false)
                            ?: $state;
                    })
                    ->searchable(query: static::getSearchableTranslationClosure('title'))
                    ->sortable(query: function (EloquentBuilder $query, string $direction, $livewire) {
                        $locale = $livewire->activeLocale ?? app()->getLocale();
                        $expression = static::getSortableTranslationExpression('title', $locale);
                        $query->orderByRaw("{$expression} {$direction}");
                    }),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('filament.resources.tile.slug'))
                    ->formatStateUsing(function ($state, Tile $record, $livewire) {
                        $locale = $livewire->activeLocale ?? app()->getLocale();

                        return $record->getTranslation('slug', $locale, false)
                            ?: $record->getTranslation('slug', 'de', false)
                            ?: $state;
                    })
                    ->searchable(query: static::getSearchableTranslationClosure('slug'))
                    ->sortable(query: function (EloquentBuilder $query, string $direction, $livewire) {
                        $locale = $livewire->activeLocale ?? app()->getLocale();
                        $expression = static::getSortableTranslationExpression('slug', $locale);
                        $query->orderByRaw("{$expression} {$direction}");
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ViewColumn::make('icon')
                    ->label(__('filament.resources.tile.icon'))
                    ->view('filament.tables.columns.icon-column')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('position')
                    ->label(__('filament.resources.tile.position'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ToggleColumn::make('is_public')
                    ->label(__('filament.resources.tile.is_public'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.resources.tile.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.resources.tile.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('handlungsfelder')
                    ->label(__('filament.resources.tile.categories'))
                    ->options(function () {
                        $locale = app()->getLocale();

                        return \App\Models\Category::query()
                            ->whereHas('group', fn ($q) => $q->where('key', 'handlungsfelder'))
                            ->get()
                            ->mapWithKeys(fn ($cat) => [
                                $cat->id => $cat->getTranslation('slug', $locale, false)
                                    ?: $cat->getTranslation('slug', 'de', false),
                            ]);
                    })
                    ->query(fn (EloquentBuilder $query, array $data): EloquentBuilder => $query->when(
                        $data['values'] ?? null,
                        fn (EloquentBuilder $q, array $values) => $q->whereHas('handlungsfelder', fn ($q) => $q->whereIn('categories.id', $values))
                    ))
                    ->searchable()
                    ->multiple(),
                Tables\Filters\TernaryFilter::make('is_public')
                    ->label(__('filament.resources.tile.is_public')),
            ])
            ->recordUrl(fn (Tile $record, $livewire) => static::getUrl('edit', [
                'record' => $record,
                'activeLocale' => $livewire->activeLocale ?? app()->getLocale(),
            ]))
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(fn (Tile $record, $livewire) => static::getUrl('edit', [
                        'record' => $record,
                        'activeLocale' => $livewire->activeLocale ?? app()->getLocale(),
                    ])),
                Tables\Actions\Action::make('view_frontend')
                    ->label(__('filament.resources.tile.actions.view_frontend'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('success')
                    ->url(function (Tile $record, $livewire) {
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
     * Build Select form fields for every CategoryGroup, ordered by group position.
     *
     * Constructs and returns an array of Select field instances (one per CategoryGroup),
     * each configured via makeCategoryGroupField and populated with the group's categories
     * ordered by their position.
     *
     * @return array<int,\Filament\Forms\Components\Select> Array of Select fields for category groups.
     */
    protected static function getCategoryGroupFields(): array
    {
        $groups = CategoryGroup::query()
            ->with(['categories' => fn ($query) => $query->orderBy('position')])
            ->orderBy('position')
            ->get();

        return $groups
            ->map(fn (CategoryGroup $group) => static::makeCategoryGroupField($group))
            ->all();
    }

    /**
     * Create a Select form field representing a CategoryGroup.
     *
     * The field is labeled with the group's translated title, populated with the group's categories (translated slugs),
     * configured for search and preload, and hydrated from a record's related categories for this group.
     *
     * @param  CategoryGroup  $group  The category group used to build the field and its options.
     * @return Select The configured Select field instance (set to allow multiple selection when the group is configured as multi).
     */
    protected static function makeCategoryGroupField(CategoryGroup $group): Select
    {
        $fieldName = static::getCategoryGroupFieldName($group->id);
        $groupId = $group->id;
        $options = $group->categories
            ->mapWithKeys(fn ($category) => [
                $category->id => $category->getTranslation('slug', app()->getLocale()),
            ])
            ->toArray();

        $field = Select::make($fieldName)
            ->label($group->getTranslation('title', app()->getLocale()))
            ->options($options)
            ->searchable()
            ->getSearchResultsUsing(function (string $search) use ($groupId): array {
                $locale = app()->getLocale();
                $loweredSearch = mb_strtolower($search);

                return \App\Models\Category::query()
                    ->where('category_group_id', $groupId)
                    ->orderBy('position')
                    ->get()
                    ->filter(fn ($cat) => str_contains(
                        mb_strtolower(
                            $cat->getTranslation('slug', $locale, false)
                                ?: $cat->getTranslation('slug', 'de', false)
                        ),
                        $loweredSearch
                    ))
                    ->mapWithKeys(fn ($cat) => [
                        $cat->id => $cat->getTranslation('slug', $locale, false)
                            ?: $cat->getTranslation('slug', 'de', false),
                    ])
                    ->toArray();
            })
            ->preload()
            ->afterStateHydrated(function (Select $component, $state, $record) use ($group): void {
                if (! $record) {
                    return;
                }

                $record->loadMissing('categories');
                $selected = $record->categories
                    ->where('category_group_id', $group->id)
                    ->pluck('id')
                    ->all();

                $component->state($group->selection_type === 'multi' ? $selected : ($selected[0] ?? null));
            });

        if ($group->selection_type === 'multi') {
            $field->multiple();
        }

        return $field;
    }

    /**
     * Builds the form field name used to store selections for a specific category group.
     *
     * @param  int  $groupId  The category group's identifier.
     * @return string The prefixed field name for the category group.
     */
    protected static function getCategoryGroupFieldName(int $groupId): string
    {
        return static::CATEGORY_GROUP_FIELD_PREFIX.$groupId;
    }

    /**
     * Get subset of the input array containing only entries whose keys start with the category group prefix.
     *
     * @param  array  $data  The input associative array to filter (e.g., form state).
     * @return array Key/value pairs from $data where keys begin with `static::CATEGORY_GROUP_FIELD_PREFIX`.
     */
    public static function extractCategoryGroupState(array $data): array
    {
        $state = [];

        foreach ($data as $key => $value) {
            if (str_starts_with($key, static::CATEGORY_GROUP_FIELD_PREFIX)) {
                $state[$key] = $value;
            }
        }

        return $state;
    }

    /**
     * Remove keys starting with the CATEGORY_GROUP_FIELD_PREFIX from the provided array.
     *
     * @param  array  $data  Associative array that may contain category-group fields.
     * @return array The array with all category-group prefixed keys removed.
     */
    public static function stripCategoryGroupState(array $data): array
    {
        foreach (array_keys($data) as $key) {
            if (str_starts_with($key, static::CATEGORY_GROUP_FIELD_PREFIX)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Synchronizes a Tile's category relationships from category-group form state.
     *
     * Reads category selections for every CategoryGroup from the provided state (keys produced by
     * getCategoryGroupFieldName), accumulates selected category IDs (honoring each group's
     * `selection_type` of `multi` or single), deduplicates them, and syncs the Tile's `categories`
     * relation to match.
     *
     * @param  Tile  $tile  The Tile model whose categories will be synchronized.
     * @param  array  $state  Associative array of category-group field values keyed by
     *                        getCategoryGroupFieldName(groupId). Values may be an integer, an array
     *                        of integers, or null/empty (which will be ignored).
     */
    public static function syncCategoryGroupSelections(Tile $tile, array $state): void
    {
        $categoryIds = [];
        $groups = CategoryGroup::query()->get(['id', 'selection_type']);

        foreach ($groups as $group) {
            $fieldName = static::getCategoryGroupFieldName($group->id);

            if (! array_key_exists($fieldName, $state)) {
                continue;
            }

            $value = $state[$fieldName];

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if ($group->selection_type === 'multi') {
                foreach ((array) $value as $id) {
                    if (is_numeric($id)) {
                        $categoryIds[] = (int) $id;
                    }
                }
            } else {
                // Single select - reject arrays, only accept numeric scalars
                if (! is_array($value) && is_numeric($value)) {
                    $categoryIds[] = (int) $value;
                }
            }
        }

        $tile->categories()->sync(array_values(array_unique($categoryIds)));
    }

    /**
     * Specify relation managers available for this resource.
     *
     * @return array<string, class-string> Array mapping relation names to relation manager class names.
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Register the resource's pages and their routes.
     *
     * @return array<string, mixed> Associative array mapping page identifiers ('index', 'create', 'edit') to their routed page classes.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTiles::route('/'),
            'create' => Pages\CreateTile::route('/create'),
            'edit' => Pages\EditTile::route('/{record}/edit'),
        ];
    }

    /**
     * Collect available background Builder block schemas from configuration.
     *
     * Only block classes listed in the configuration key "filament-fabricator.page-blocks.register"
     * that are in the allowed set (FAQBlock, IntroTextBlock, SliderBlock, TextImageBlock) and expose
     * a static `getBlockSchema()` method will be included.
     *
     * @return array<\Filament\Forms\Components\Builder\Block> The Builder block schemas to use in the background blocks.
     */
    protected static function getBackgroundBlockSchemas(): array
    {
        $blocks = [];
        $registeredBlocks = config('filament-fabricator.page-blocks.register', []);
        $allowedBlocks = [
            FAQBlock::class,
            IntroTextBlock::class,
            SliderBlock::class,
            TextImageBlock::class,
        ];
        $filteredBlocks = array_values(array_intersect($allowedBlocks, $registeredBlocks));

        foreach ($filteredBlocks as $blockClass) {
            if (class_exists($blockClass) && method_exists($blockClass, 'getBlockSchema')) {
                $blocks[] = $blockClass::getBlockSchema();
            }
        }

        return $blocks;
    }

    /**
     * Resolve the Tile associated with a metric-related record or a Livewire component.
     *
     * @param  Model|null  $record  A Tile, MetricDefinition, MetricValue, or null; the method will resolve the related Tile when possible.
     * @param  mixed  $livewire  Optional Livewire component; if provided and it exposes a `getRecord()` method that returns a Tile, that Tile will be returned.
     * @return Tile|null The associated Tile when found, or null otherwise.
     */
    protected static function resolveTileForMetricValue(?Model $record, $livewire): ?Tile
    {
        if ($record instanceof Tile) {
            return $record;
        }

        if ($record instanceof MetricDefinition) {
            return $record->tile;
        }

        if ($record instanceof MetricValue) {
            $definition = $record->relationLoaded('metricDefinition')
                ? $record->metricDefinition
                : $record->metricDefinition()->first();

            return $definition?->tile;
        }

        if ($livewire && method_exists($livewire, 'getRecord')) {
            $livewireRecord = $livewire->getRecord();
            if ($livewireRecord instanceof Tile) {
                return $livewireRecord;
            }
        }

        return null;
    }

    /**
     * Ensure a TileYear exists for the given tile and numeric year, and replace `tile_year_id` in the provided data with that TileYear's id.
     *
     * @param  array<string,mixed>  $data  Input data array which may contain a numeric `tile_year_id` representing a year.
     * @param  Tile|null  $tile  The Tile to associate the year with; if null, the data is returned unchanged.
     * @return array<string,mixed> The (possibly modified) data array with `tile_year_id` set to the corresponding TileYear id when applicable.
     */
    protected static function resolveMetricValueTileYearId(array $data, ?Tile $tile): array
    {
        $year = is_numeric($data['tile_year_id'] ?? null) ? (int) $data['tile_year_id'] : null;

        if ($year === null || ! $tile) {
            return $data;
        }

        $tileYear = TileYear::firstOrCreate([
            'tile_id' => $tile->id,
            'year' => $year,
        ]);

        $data['tile_year_id'] = $tileYear->id;

        return $data;
    }
}
