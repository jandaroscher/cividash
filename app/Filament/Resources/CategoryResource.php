<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSortableTranslations;
use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use App\Models\Category;
use App\Models\CategoryGroup;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoryResource extends Resource
{
    use HasSortableTranslations;
    use Translatable;

    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Kategorien';
    protected static ?int $navigationSort = 10;

    /**
     * Provide the localized navigation label for the Category resource.
     *
     * @return string The navigation label for the resource in the current locale.
     */
    public static function getNavigationLabel(): string
    {
        return __('filament.resources.category.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.category.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.category.plural_model_label');
    }

    /**
         * Configure and return the Filament form schema for the Category resource.
         *
         * The form includes inputs for category group selection, key, slug (title), icon
         * (locale-aware state handling and persistence), color (visible when the selected
         * group provides color), is_active toggle, and position.
         *
         * @param Form $form The base Filament form instance to configure.
         * @return Form The configured form instance containing the Category resource fields.
         */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('category_group_id')
                    ->label(__('filament.resources.category.group'))
                    ->options(fn () => CategoryGroup::query()
                        ->orderBy('position')
                        ->get()
                        ->mapWithKeys(fn ($group) => [
                            $group->id => $group->getTranslation('title', app()->getLocale()),
                        ])
                        ->toArray())
                    ->default(fn () => request()->query('category_group_id'))
                    ->required()
                    ->searchable()
                    ->live(),
                Forms\Components\TextInput::make('key')
                    ->label(__('filament.resources.category.key'))
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->label(__('filament.resources.category.title'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\FileUpload::make('icon')
                    ->label(__('filament.resources.category.icon'))
                    ->disk('public')
                    ->directory('categories')
                    ->image()
                    ->preserveFilenames()
                    ->required(false)
                    ->formatStateUsing(function ($state) {
                        $iconPath = null;

                        if (is_array($state)) {
                            if (array_is_list($state)) {
                                $iconPath = $state[0] ?? null;
                            } else {
                                $iconPath = $state[app()->getLocale()] ?? $state['de'] ?? $state['en'] ?? null;
                            }
                        } elseif (is_string($state)) {
                            $decoded = json_decode($state, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $iconPath = $decoded[app()->getLocale()] ?? $decoded['de'] ?? $decoded['en'] ?? null;
                            } else {
                                $iconPath = $state;
                            }
                        }

                        return $iconPath ? [$iconPath] : [];
                    })
                    ->dehydrateStateUsing(function ($state, $record) {
                        $path = is_array($state) ? ($state[0] ?? null) : $state;

                        if (! $path) {
                            return null;
                        }

                        // New record - create JSON structure with current locale
                        if (! $record || ! is_string($record->icon)) {
                            return json_encode([app()->getLocale() => $path], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }

                        // Existing record with JSON icon - update locale
                        $decoded = json_decode($record->icon, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $locale = app()->getLocale();
                            $decoded[$locale] = $path;

                            return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }

                        // Fallback for non-JSON existing icons
                        return $path;
                    }),
                Forms\Components\ColorPicker::make('color')
                    ->label(__('filament.resources.category.color'))
                    ->required(false)
                    ->hex()
                    ->visible(fn (Get $get): bool => (bool) CategoryGroup::find($get('category_group_id'))?->is_color_source),
                Forms\Components\Toggle::make('is_active')
                    ->label(__('filament.resources.category.is_active'))
                    ->default(true),
                Forms\Components\TextInput::make('position')
                    ->label(__('filament.resources.category.position'))
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    /**
         * Configure the resource table's columns, filters, row actions, and bulk actions.
         *
         * @param \Filament\Tables\Table $table The table to configure.
         * @return \Filament\Tables\Table The configured table instance.
         */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('filament.resources.category.title'))
                    ->formatStateUsing(function ($state, Category $record) {
                        return $record->getTranslation('slug', app()->getLocale(), false)
                            ?: $record->getTranslation('slug', 'de', false)
                            ?: $state;
                    })
                    ->searchable()
                    ->sortable(query: function (Builder $query, string $direction) {
                        $expression = static::getSortableTranslationExpression('slug', app()->getLocale());
                        $query->orderByRaw("{$expression} {$direction}");
                    }),
                Tables\Columns\TextColumn::make('group.title')
                    ->label(__('filament.resources.category.group'))
                    ->formatStateUsing(fn ($state, $record) => $state ?? $record->group?->getTranslation('title', app()->getLocale()))
                    ->sortable(query: function (Builder $query, string $direction) {
                        $expression = static::getSortableTranslationExpression('category_groups.title', app()->getLocale());
                        $query->select('categories.*')
                            ->leftJoin('category_groups', 'categories.category_group_id', '=', 'category_groups.id')
                            ->orderByRaw("{$expression} {$direction}");
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('key')
                    ->label(__('filament.resources.category.key'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ImageColumn::make('icon')
                    ->label(__('filament.resources.category.icon'))
                    ->disk('public')
                    ->square()
                    ->size(40),
                Tables\Columns\ColorColumn::make('color')
                    ->label(__('filament.resources.category.color'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('filament.resources.category.is_active'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('position')
                    ->label(__('filament.resources.category.position'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.resources.category.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.resources.category.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('filament.resources.category.is_active')),
                Tables\Filters\SelectFilter::make('category_group_id')
                    ->label(__('filament.resources.category.group'))
                    ->relationship('group', 'title')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('title', app()->getLocale()))
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }

    /**
     * Indicates whether the resource should be shown in the Filament navigation.
     *
     * @return bool `true` if the resource should be registered in navigation, `false` otherwise.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}