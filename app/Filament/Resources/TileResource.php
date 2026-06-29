<?php

namespace App\Filament\Resources;

use App\Enums\TimeGranularity;
use App\Exceptions\Integration\ForeignProvenanceException;
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
use App\Models\TimePeriod;
use App\Services\Integration\PublishService;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
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
use Filament\Notifications\Notification;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class TileResource extends Resource
{
    use HasBlockActiveToggleAction;
    use HasSortableTranslations;
    use Translatable;

    protected const CATEGORY_GROUP_FIELD_PREFIX = 'category_group_';

    protected static ?string $model = Tile::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

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
                                        Textarea::make('hint')
                                            ->label(__('filament.resources.tile.hint'))
                                            ->rows(3),
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
                                            ->collapsed()
                                            ->blockNumbers(false),
                                    ]),

                                // Tab 3: metrics
                                Tabs\Tab::make(__('filament.tabs.metrics'))
                                    ->schema([
                                        Select::make('time_granularity')
                                            ->label(__('filament.resources.tile.time_granularity'))
                                            ->options(TimeGranularity::filamentOptions())
                                            ->default('year')
                                            ->required()
                                            ->disabled(fn (?Tile $record): bool => $record !== null && $record->timePeriods()->whereHas('metricValues')->exists())
                                            ->helperText(fn (?Tile $record): string => $record !== null && $record->timePeriods()->whereHas('metricValues')->exists()
                                                ? __('filament.resources.tile.time_granularity_helper')
                                                : __('filament.resources.tile.time_granularity_helper_editable'))
                                            ->live(),
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
                                                TextInput::make('label')
                                                    ->label(__('filament.resources.tile.label'))
                                                    ->required()
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (?string $state, \Filament\Forms\Set $set, ?MetricDefinition $record): void {
                                                        if ($record === null && filled($state)) {
                                                            $set('metric_key', \Illuminate\Support\Str::slug($state));
                                                        }
                                                    }),
                                                TextInput::make('metric_key')
                                                    ->label(__('filament.resources.tile.metric_key'))
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->required()
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
                                                        if ($record && $record->relationLoaded('timePeriod') && $record->timePeriod) {
                                                            return $record->timePeriod->label ?? $record->timePeriod->period_key;
                                                        }
                                                        if (isset($state['time_period_id']) && is_numeric($state['time_period_id'])) {
                                                            $timePeriod = TimePeriod::find($state['time_period_id']);

                                                            return $timePeriod ? ($timePeriod->label ?? $timePeriod->period_key) : (string) $state['time_period_id'];
                                                        }

                                                        return null;
                                                    })
                                                    ->extraItemActions([
                                                        static::getBlockActiveToggleAction(),
                                                    ])
                                                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data, $record, $livewire): array {
                                                        $tile = static::resolveTileForMetricValue($record, $livewire);

                                                        return static::resolveMetricValueTimePeriodId($data, $tile);
                                                    })
                                                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data, $record, $livewire): array {
                                                        $tile = static::resolveTileForMetricValue($record, $livewire);

                                                        return static::resolveMetricValueTimePeriodId($data, $tile);
                                                    })
                                                    ->schema([
                                                        Hidden::make('is_active')
                                                            ->default(true)
                                                            ->afterStateHydrated(function (Hidden $component, $state): void {
                                                                if ($state === null) {
                                                                    $component->state(true);
                                                                }
                                                            }),
                                                        TextInput::make('time_period_id')
                                                            ->label(__('filament.resources.tile.period_key'))
                                                            ->placeholder(function (Get $get) {
                                                                $granularity = $get('../../../../time_granularity') ?? 'year';
                                                                $enum = TimeGranularity::tryFrom($granularity);

                                                                return $enum?->inputPlaceholder() ?? 'z.B. 2023';
                                                            })
                                                            ->helperText(function (Get $get) {
                                                                $granularity = $get('../../../../time_granularity') ?? 'year';
                                                                $enum = TimeGranularity::tryFrom($granularity);

                                                                return $enum?->inputHelperText() ?? '';
                                                            })
                                                            ->rule(function (Get $get) {
                                                                return function (string $attribute, $value, Closure $fail) use ($get) {
                                                                    if (! $value) {
                                                                        return;
                                                                    }

                                                                    // Validate input format (German format)
                                                                    $granularity = $get('../../../../time_granularity') ?? 'year';
                                                                    $enum = TimeGranularity::tryFrom($granularity);
                                                                    if ($enum && ! $enum->isValidInput($value)) {
                                                                        $fail(__('filament.resources.tile.invalid_period_format', [
                                                                            'format' => $enum->inputHelperText(),
                                                                        ]));

                                                                        return;
                                                                    }

                                                                    // Normalize for duplicate check
                                                                    $normalized = $enum ? ($enum->normalizeInput($value) ?? $value) : $value;

                                                                    // Check for duplicates
                                                                    $parent = $get('../../');
                                                                    $siblings = is_array($parent) ? ($parent['metricValues'] ?? []) : [];
                                                                    if (! is_array($siblings)) {
                                                                        return;
                                                                    }
                                                                    $count = collect($siblings)
                                                                        ->filter(function ($item) use ($enum, $normalized) {
                                                                            $other = $item['time_period_id'] ?? null;
                                                                            if ($other === null) {
                                                                                return false;
                                                                            }
                                                                            $otherNormalized = $enum ? ($enum->normalizeInput((string) $other) ?? (string) $other) : (string) $other;

                                                                            return $otherNormalized === $normalized;
                                                                        })
                                                                        ->count();
                                                                    if ($count > 1) {
                                                                        $fail(__('filament.resources.tile.duplicate_period'));
                                                                    }
                                                                };
                                                            })
                                                            ->afterStateHydrated(function (TextInput $component, $state, $record): void {
                                                                // Show stored period_key in German display format
                                                                if ($record && $record->relationLoaded('timePeriod') && $record->timePeriod) {
                                                                    $granularity = $record->timePeriod->granularity;
                                                                    $enum = $granularity instanceof TimeGranularity ? $granularity : TimeGranularity::tryFrom($granularity ?? 'year');
                                                                    $component->state($enum?->toDisplayFormat($record->timePeriod->period_key) ?? $record->timePeriod->period_key);

                                                                    return;
                                                                }

                                                                if (is_numeric($state)) {
                                                                    $timePeriod = TimePeriod::find($state);
                                                                    if ($timePeriod) {
                                                                        $granularity = $timePeriod->granularity;
                                                                        $enum = $granularity instanceof TimeGranularity ? $granularity : TimeGranularity::tryFrom($granularity ?? 'year');
                                                                        $component->state($enum?->toDisplayFormat($timePeriod->period_key) ?? $timePeriod->period_key);
                                                                    }
                                                                }
                                                            })
                                                            ->dehydrateStateUsing(fn ($state) => $state)
                                                            ->required(),
                                                        TextInput::make('value')
                                                            ->label(__('filament.resources.tile.value'))
                                                            ->numeric()
                                                            ->required(),
                                                    ])
                                                    ->collapsible()
                                                    ->collapsed(),
                                            ])
                                            ->collapsible()
                                            ->collapsed(),
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
                    ->dateTime(__('filament.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.resources.tile.updated_at'))
                    ->dateTime(__('filament.date_time_format'))
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
                Tables\Actions\Action::make('publishToCore')
                    ->label(__('filament.resources.tile.actions.publish'))
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('primary')
                    ->visible(fn (): bool => static::canPublishToCore())
                    // Server-side gate: visible() is render-only, so authorize()
                    // also blocks direct Livewire invocation by non-admins.
                    ->authorize(fn (): bool => static::canUserPublishToCore())
                    ->requiresConfirmation()
                    ->modalHeading(__('filament.resources.tile.actions.publish_confirm_heading'))
                    ->modalDescription(__('filament.resources.tile.actions.publish_confirm_description'))
                    ->modalSubmitActionLabel(__('filament.resources.tile.actions.publish_confirm_submit'))
                    ->action(fn (Tile $record) => static::handlePublishToCore($record)),
                Tables\Actions\DeleteAction::make(),
            ])
            ->reorderable('position')
            ->defaultSort('position')
            ->reorderRecordsTriggerAction(
                fn (Tables\Actions\Action $action, bool $isReordering) => $action
                    ->link()
                    ->label($isReordering ? __('filament.actions.stop_sorting') : __('filament.actions.start_sorting'))
                    ->color('primary')
            )
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
     * @return Select The configured Select field instance (always allows multiple selection).
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

                $component->state($selected);
            })
            ->multiple();

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
     * getCategoryGroupFieldName), accumulates selected category IDs, deduplicates them, and syncs
     * the Tile's `categories` relation to match.
     *
     * @param  Tile  $tile  The Tile model whose categories will be synchronized.
     * @param  array  $state  Associative array of category-group field values keyed by
     *                        getCategoryGroupFieldName(groupId). Values may be an integer, an array
     *                        of integers, or null/empty (which will be ignored).
     */
    public static function syncCategoryGroupSelections(Tile $tile, array $state): void
    {
        $categoryIds = [];
        $groups = CategoryGroup::query()->get(['id']);

        foreach ($groups as $group) {
            $fieldName = static::getCategoryGroupFieldName($group->id);

            if (! array_key_exists($fieldName, $state)) {
                continue;
            }

            $value = $state[$fieldName];

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            foreach ((array) $value as $id) {
                if (is_numeric($id)) {
                    $categoryIds[] = (int) $id;
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
                $block = $blockClass::getBlockSchema();
                $defaultLabel = $block->getLabel();
                $existingSchema = $block->getChildComponents();
                static::patchFileUploadsForTranslatable($existingSchema);
                $block->schema([
                    TextInput::make('jump_mark_label')
                        ->label(__('filament.blocks.jump_mark_label'))
                        ->helperText(__('filament.blocks.jump_mark_label_helper'))
                        ->maxLength(255)
                        ->live(onBlur: true),
                    ...$existingSchema,
                ])->label(function (?array $state) use ($defaultLabel): string {
                    $heading = $state['heading'] ?? $state['title'] ?? null;
                    $jumpMark = $state['jump_mark_label'] ?? null;

                    if ($heading && $jumpMark) {
                        return "{$heading} (#{$jumpMark})";
                    }
                    if ($jumpMark) {
                        return "#{$jumpMark}";
                    }

                    return $heading ?? $defaultLabel;
                });
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * Patch FileUpload components to handle string state from translatable Builder fields.
     *
     * When background_blocks is translatable, the content driver may set FileUpload state
     * as a raw string instead of an array, causing a TypeError on save. This ensures
     * FileUpload state is always wrapped in an array before dehydration.
     *
     * @param  array  $components  The form components to patch (modified in place via Filament's fluent API).
     */
    protected static function patchFileUploadsForTranslatable(array $components): void
    {
        foreach ($components as $component) {
            if ($component instanceof BaseFileUpload) {
                $component->dehydrateStateUsing(static function (BaseFileUpload $component, string|array|null $state) {
                    if (is_string($state)) {
                        $state = filled($state) ? [$state] : [];
                    }

                    $files = array_values($state ?? []);

                    if ($component->isMultiple()) {
                        return $files;
                    }

                    return $files[0] ?? null;
                });
            }

            if (method_exists($component, 'getChildComponents')) {
                static::patchFileUploadsForTranslatable($component->getChildComponents());
            }
        }
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

    protected static function resolveMetricValueTimePeriodId(array $data, ?Tile $tile): array
    {
        $input = $data['time_period_id'] ?? null;

        if ($input === null || ! is_string($input) || $input === '' || ! $tile) {
            return $data;
        }

        $timePeriod = app(\App\Services\TimePeriodService::class)->resolveOrCreate($input, $tile);

        $data['time_period_id'] = $timePeriod->id;

        return $data;
    }

    /**
     * Whether the "publish to CORE" action should be offered.
     *
     * Gated on the CIVITAS integration being enabled and using the NGSI-LD
     * driver (the only write-capable one — SensorThings is read-only).
     */
    public static function canPublishToCore(): bool
    {
        return (bool) config('integrations.civitas.enabled')
            && config('integrations.civitas.driver', 'ngsi-ld') === 'ngsi-ld';
    }

    /**
     * Server-side authorization gate for the "publish to CORE" action.
     *
     * Used by authorize() on both the EditTile header action and the ListTiles
     * row action. Unlike visible() (render-only), this also blocks a direct
     * Livewire invocation. Mirrors the admin gate used by ManageIntegrations.
     */
    public static function canUserPublishToCore(): bool
    {
        return static::canPublishToCore() && (bool) auth()->user()?->is_admin;
    }

    /**
     * Publish a Tile to CIVITAS/CORE and surface the outcome as a notification.
     *
     * Shared by the EditTile header action and the ListTiles row action. The
     * actual write, provenance stamping and idempotency live in PublishService;
     * this only translates the outcome into user-facing notifications. Only this
     * explicit user click writes to the live broker.
     */
    public static function handlePublishToCore(Tile $record): void
    {
        try {
            $result = app(PublishService::class)->publishTile($record);

            if ($result->skipped) {
                Notification::make()
                    ->title(__('filament.resources.tile.actions.publish_skipped'))
                    ->body(__('filament.resources.tile.actions.publish_skipped_body'))
                    ->info()
                    ->send();

                return;
            }

            Notification::make()
                ->title(__('filament.resources.tile.actions.publish_success'))
                ->body(__('filament.resources.tile.actions.publish_success_body', ['id' => $result->externalId]))
                ->success()
                ->send();
        } catch (ForeignProvenanceException $e) {
            Notification::make()
                ->title(__('filament.resources.tile.actions.publish_foreign_provenance'))
                ->body(__('filament.resources.tile.actions.publish_foreign_provenance_body'))
                ->danger()
                ->send();
        } catch (RequestException $e) {
            // The broker rejected the write. The exception message can contain
            // the token URL and the raw IdP/broker response, so it is logged
            // server-side only; the user sees a generic message + HTTP status.
            Log::error('CORE publish failed (broker error).', [
                'tile_id' => $record->id,
                'tenant_id' => $record->tenant_id,
                'status' => $e->response?->status(),
                'exception' => $e,
            ]);

            Notification::make()
                ->title(__('filament.resources.tile.actions.publish_error'))
                ->body(__('filament.resources.tile.actions.publish_error_body', ['status' => $e->response?->status() ?? '—']))
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            // Connection failures and any other error: never surface the raw
            // message (it may carry the broker/IdP URL). Log it, show a generic
            // notification.
            Log::error('CORE publish failed (unexpected error).', [
                'tile_id' => $record->id,
                'tenant_id' => $record->tenant_id,
                'exception' => $e,
            ]);

            Notification::make()
                ->title(__('filament.resources.tile.actions.publish_error'))
                ->body(__('filament.resources.tile.actions.publish_error_generic'))
                ->danger()
                ->send();
        }
    }
}
