<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSortableTranslations;
use App\Filament\Resources\CategoryGroupResource\Pages;
use App\Filament\Resources\CategoryGroupResource\RelationManagers;
use App\Models\CategoryGroup;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoryGroupResource extends Resource
{
    use HasSortableTranslations;
    use Translatable;

    protected static ?string $model = CategoryGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 9;

    /**
     * Returns the navigation label used for this resource in the admin navigation.
     *
     * @return string The navigation label for the resource.
     */
    public static function getNavigationLabel(): string
    {
        return __('filament.resources.category_group.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.categories');
    }

    /**
     * Get the translated singular label for the resource's model.
     *
     * @return string The model label used in the UI.
     */
    public static function getModelLabel(): string
    {
        return __('filament.resources.category_group.model_label');
    }

    /**
     * Get the translated plural label for the CategoryGroup model.
     *
     * @return string The translated plural model label.
     */
    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.category_group.plural_model_label');
    }

    /**
     * Configure the resource's create/edit form schema.
     *
     * Contains fields for `key`, `title`, `selection_type`, `is_filterable`, `is_color_source`, `is_active`, and `position`.
     * The `is_color_source` field enforces that at most one color source exists for the same tenant or globally.
     *
     * @return Form The configured form instance with the resource's schema.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('key')
                    ->label(__('filament.resources.category_group.key'))
                    ->required()
                    ->maxLength(255)
                    ->regex('/^[a-z][a-z0-9_-]*$/')
                    ->helperText(__('filament.resources.category_group.key_helper'))
                    ->disabled(fn ($record) => $record !== null),
                Forms\Components\TextInput::make('title')
                    ->label(__('filament.resources.category_group.title'))
                    ->required(),
                Forms\Components\Select::make('selection_type')
                    ->label(__('filament.resources.category_group.selection_type'))
                    ->options([
                        'single' => __('filament.resources.category_group.selection_single'),
                        'multi' => __('filament.resources.category_group.selection_multi'),
                    ])
                    ->required()
                    ->default('multi'),
                Forms\Components\Toggle::make('is_filterable')
                    ->label(__('filament.resources.category_group.is_filterable'))
                    ->default(false),
                Forms\Components\Toggle::make('is_color_source')
                    ->label(__('filament.resources.category_group.is_color_source'))
                    ->default(false)
                    ->rule(function ($record) {
                        return function (string $attribute, $value, $fail) use ($record): void {
                            if (! $value) {
                                return;
                            }

                            $tenantId = Filament::getTenant()?->id;

                            $query = CategoryGroup::query()
                                ->where('is_color_source', true)
                                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNull('tenant_id'));

                            if ($record) {
                                $query->whereKeyNot($record->id);
                            }

                            if ($query->exists()) {
                                $fail(__('filament.resources.category_group.color_source_unique'));
                            }
                        };
                    }),
                Forms\Components\Toggle::make('is_active')
                    ->label(__('filament.resources.category_group.is_active'))
                    ->default(true),
                Forms\Components\TextInput::make('position')
                    ->label(__('filament.resources.category_group.position'))
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    /**
     * Configure table columns, row actions, and bulk actions for the CategoryGroup resource.
     *
     * @param  \Filament\Tables\Table  $table  The table instance to configure.
     * @return \Filament\Tables\Table The configured table with columns, filters, actions, and bulk actions.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label(__('filament.resources.category_group.key'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label(__('filament.resources.category_group.title'))
                    ->formatStateUsing(function ($state, CategoryGroup $record, $livewire) {
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
                Tables\Columns\TextColumn::make('selection_type')
                    ->label(__('filament.resources.category_group.selection_type'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_filterable')
                    ->label(__('filament.resources.category_group.is_filterable'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_color_source')
                    ->label(__('filament.resources.category_group.is_color_source'))
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('filament.resources.category_group.is_active'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('position')
                    ->label(__('filament.resources.category_group.position'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.resources.category_group.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.resources.category_group.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('filament.resources.category_group.is_active')),
                Tables\Filters\TernaryFilter::make('is_filterable')
                    ->label(__('filament.resources.category_group.is_filterable')),
                Tables\Filters\TernaryFilter::make('is_color_source')
                    ->label(__('filament.resources.category_group.is_color_source')),
                Tables\Filters\SelectFilter::make('selection_type')
                    ->label(__('filament.resources.category_group.selection_type'))
                    ->options([
                        'single' => __('filament.resources.category_group.selection_single'),
                        'multi' => __('filament.resources.category_group.selection_multi'),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(fn (CategoryGroup $record, $livewire) => static::getUrl('edit', [
                        'record' => $record,
                        'activeLocale' => $livewire->activeLocale ?? app()->getLocale(),
                    ])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * List relation managers exposed by this resource.
     *
     * @return array<int, class-string> Array of relation manager class names.
     */
    public static function getRelations(): array
    {
        return [
            RelationManagers\CategoriesRelationManager::class,
        ];
    }

    /**
     * Register the resource's page routes for list, create, and edit screens.
     *
     * @return array Mapping of page keys ('index', 'create', 'edit') to their route definitions.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategoryGroups::route('/'),
            'create' => Pages\CreateCategoryGroup::route('/create'),
            'edit' => Pages\EditCategoryGroup::route('/{record}/edit'),
        ];
    }
}
