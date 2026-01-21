<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TileResource\Pages;
use App\Filament\Resources\TileResource\RelationManagers;
use App\Filament\Concerns\HasBlockActiveToggleAction;
use App\Filament\Fabricator\PageBlocks\FAQBlock;
use App\Filament\Fabricator\PageBlocks\IntroTextBlock;
use App\Filament\Fabricator\PageBlocks\SliderBlock;
use App\Filament\Fabricator\PageBlocks\TextImageBlock;
use App\Models\CategoryGroup;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tile;
use App\Models\TileYear;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

use Filament\Resources\Concerns\Translatable;

class TileResource extends Resource
{
    use Translatable;
    use HasBlockActiveToggleAction;

    protected const CATEGORY_GROUP_FIELD_PREFIX = 'category_group_';

    protected static ?string $model = Tile::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Inhalte';
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

    public static function getModelLabel(): string
    {
        return __('filament.resources.tile.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.tile.plural_model_label');
    }

    /**
     * Configure the form schema for the Tile resource with three tabs: Tile (basic fields and relationships), Background Page (block builder), and Metrics (nested repeaters for year groups and metrics).
     *
     * @param Form $form The form instance to configure.
     * @return Form The configured form containing the Tabs schema, a Builder for background blocks, and nested Repeaters for tile years and metrics.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->schema([
                        Tabs::make(__('filament.tabs.contents'))
                            ->tabs([
                                // Tab 1: tile
                                Tabs\Tab::make(__('filament.tabs.tile'))
                                    ->schema([
                                        ...static::getCategoryGroupFields(),
                                        RichEditor::make('description')
                                            ->label(__('filament.resources.tile.description')),
                                        FileUpload::make('icon')
                                            ->label(__('filament.resources.tile.icon'))
                                            ->disk('public')
                                            ->directory('tiles')
                                            ->preserveFilenames()
                                            ->required(false),
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
                                            ->addActionLabel(__('filament.resources.tile.background_blocks_add'))
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
                                            ->label(__('filament.resources.tile.metrics_label'))
                                            ->itemLabel(fn(array $state): ?string => $state['label'] ?? null)
                                            ->extraItemActions([
                                                static::getBlockActiveToggleAction(),
                                            ])
                                            ->schema([
                                                TextInput::make('metric_key')
                                                    ->label(__('filament.resources.tile.metric_key'))
                                                    ->required(),
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
                                                    ->label(__('filament.resources.tile.metric_values'))
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
                                                    ->collapsible()
                                            ])
                                            ->collapsible()
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
                                            $localePath = '$."' . $locale . '"';

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
     * @param \Filament\Tables\Table $table The table instance to configure.
     * @return \Filament\Tables\Table The configured table instance.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('filament.resources.tile.title'))
                    ->formatStateUsing(function ($state, Tile $record) {
                        return $record->getTranslation('title', app()->getLocale(), false)
                            ?: $record->getTranslation('title', 'de', false)
                            ?: $state;
                    })
                    ->searchable()
                    ->sortable(query: function (EloquentBuilder $query, string $direction) {
                        $expression = static::getSortableTranslationExpression('title', app()->getLocale());
                        $query->orderByRaw("{$expression} {$direction}");
                    }),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('filament.resources.tile.slug'))
                    ->formatStateUsing(function ($state, Tile $record) {
                        return $record->getTranslation('slug', app()->getLocale(), false)
                            ?: $record->getTranslation('slug', 'de', false)
                            ?: $state;
                    })
                    ->searchable()
                    ->sortable(query: function (EloquentBuilder $query, string $direction) {
                        $expression = static::getSortableTranslationExpression('slug', app()->getLocale());
                        $query->orderByRaw("{$expression} {$direction}");
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ImageColumn::make('icon')
                    ->label(__('filament.resources.tile.icon'))
                    ->disk('public')
                    ->square()
                    ->size(40)
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->relationship('handlungsfelder', 'slug')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('slug', app()->getLocale()))
                    ->searchable()
                    ->preload()
                    ->multiple(),
                Tables\Filters\TernaryFilter::make('is_public')
                    ->label(__('filament.resources.tile.is_public')),
            ])
            ->recordUrl(fn (Tile $record) => static::getUrl('edit', ['record' => $record]))
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_frontend')
                    ->label(__('filament.resources.tile.actions.view_frontend'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('success')
                    ->url(function (Tile $record, $livewire) {
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
     * @param CategoryGroup $group The category group used to build the field and its options.
     * @return Select The configured Select field instance (set to allow multiple selection when the group is configured as multi).
     */
    protected static function makeCategoryGroupField(CategoryGroup $group): Select
    {
        $fieldName = static::getCategoryGroupFieldName($group->id);
        $options = $group->categories
            ->mapWithKeys(fn ($category) => [
                $category->id => $category->getTranslation('slug', app()->getLocale()),
            ])
            ->toArray();

        $field = Select::make($fieldName)
            ->label($group->getTranslation('title', app()->getLocale()))
            ->options($options)
            ->searchable()
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
     * @param int $groupId The category group's identifier.
     * @return string The prefixed field name for the category group.
     */
    protected static function getCategoryGroupFieldName(int $groupId): string
    {
        return static::CATEGORY_GROUP_FIELD_PREFIX . $groupId;
    }

    /**
     * Extracts entries whose keys are prefixed for category groups from the given data array.
     *
     * Filters the provided associative array and returns a new array containing only keys
     * that start with the class constant CATEGORY_GROUP_FIELD_PREFIX and their corresponding values.
     *
     * @param array $data The input associative array (e.g., form state).
     * @return array An array of key/value pairs where keys begin with the category group prefix.
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
     * Remove any keys that start with the CATEGORY_GROUP_FIELD_PREFIX from the provided array.
     *
     * @param array $data The input associative array potentially containing category-group fields.
     * @return array The input array with all category-group prefixed keys removed.
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
     * @param Tile  $tile  The Tile model whose categories will be synchronized.
     * @param array $state Associative array of category-group field values keyed by
     *                     getCategoryGroupFieldName(groupId). Values may be an integer, an array
     *                     of integers, or null/empty (which will be ignored).
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
         * Load and return Builder block schemas registered via the Fabricator configuration.
         *
         * Reads the `filament-fabricator.page-blocks.register` config, calls `getBlockSchema()` on each valid block class,
         * and returns the collected Builder block schemas.
         *
         * @return array<\Filament\Forms\Components\Builder\Block> The array of Builder block schemas to use in the background blocks.
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

    protected static function getSortableTranslationExpression(string $column, ?string $locale = null): string
    {
        $allowedColumns = ['title', 'slug'];

        if (! in_array($column, $allowedColumns, true)) {
            throw new \InvalidArgumentException("Invalid sortable column: {$column}");
        }

        $locale = static::normalizeSortLocale($locale);
        $localePath = '$."' . $locale . '"';
        $fallbackPath = '$."de"';
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return sprintf(
                "COALESCE(NULLIF(json_extract(%s, '%s'), ''), NULLIF(json_extract(%s, '%s'), ''))",
                $column,
                $localePath,
                $column,
                $fallbackPath
            );
        }

        if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
            return sprintf(
                "COALESCE(NULLIF(%s->>'%s', ''), NULLIF(%s->>'%s', ''))",
                $column,
                $locale,
                $column,
                'de'
            );
        }

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
     * @param array<string, mixed> $data
     * @return array<string, mixed>
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