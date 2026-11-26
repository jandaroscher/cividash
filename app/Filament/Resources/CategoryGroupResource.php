<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSortableTranslations;
use App\Filament\Resources\CategoryGroupResource\Pages\CreateCategoryGroup;
use App\Filament\Resources\CategoryGroupResource\Pages\EditCategoryGroup;
use App\Filament\Resources\CategoryGroupResource\Pages\ListCategoryGroups;
use App\Filament\Resources\CategoryGroupResource\RelationManagers\CategoriesRelationManager;
use App\Models\CategoryGroup;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class CategoryGroupResource extends Resource
{
    use HasSortableTranslations;
    use Translatable;

    protected static ?string $model = CategoryGroup::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

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
     * Contains fields for `key`, `title`, `is_active`, and `position`.
     *
     * @return Schema The configured form instance with the resource's schema.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.resources.category_group.section_group'))
                    ->columns(1)
                    ->schema([
                        TextInput::make('title')
                            ->label(__('filament.resources.category_group.title'))
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, $record): void {
                                if ($record === null && filled($state)) {
                                    $set('key', Str::slug($state));
                                }
                            }),
                        TextInput::make('key')
                            ->label(__('filament.resources.category_group.key'))
                            ->required()
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated()
                            ->unique(
                                table: CategoryGroup::class,
                                column: 'key',
                                ignorable: fn ($record) => $record,
                                modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', Filament::getTenant()?->id),
                            ),
                        Toggle::make('is_active')
                            ->label(__('filament.resources.category_group.is_active'))
                            ->default(true),
                    ]),
            ]);
    }

    /**
     * Configure table columns, row actions, and bulk actions for the CategoryGroup resource.
     *
     * @param  Table  $table  The table instance to configure.
     * @return Table The configured table with columns, filters, actions, and bulk actions.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label(__('filament.resources.category_group.key'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
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
                ToggleColumn::make('is_active')
                    ->label(__('filament.resources.category_group.is_active'))
                    ->sortable(),
                TextColumn::make('position')
                    ->label(__('filament.resources.category_group.position'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament.resources.category_group.created_at'))
                    ->dateTime(__('filament.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('filament.resources.category_group.updated_at'))
                    ->dateTime(__('filament.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('filament.resources.category_group.is_active')),
            ])
            ->recordActions([
                EditAction::make()
                    ->url(fn (CategoryGroup $record, $livewire) => static::getUrl('edit', [
                        'record' => $record,
                        'activeLocale' => $livewire->activeLocale ?? app()->getLocale(),
                    ])),
                DeleteAction::make(),
            ])
            ->reorderable('position')
            ->defaultSort('position')
            ->reorderRecordsTriggerAction(
                fn (Action $action, bool $isReordering) => $action
                    ->link()
                    ->label($isReordering ? __('filament.actions.stop_sorting') : __('filament.actions.start_sorting'))
                    ->color('primary')
            )
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            CategoriesRelationManager::class,
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
            'index' => ListCategoryGroups::route('/'),
            'create' => CreateCategoryGroup::route('/create'),
            'edit' => EditCategoryGroup::route('/{record}/edit'),
        ];
    }
}
