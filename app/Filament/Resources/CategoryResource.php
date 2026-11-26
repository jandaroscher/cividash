<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSortableTranslations;
use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Settings\BrandingSettings;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class CategoryResource extends Resource
{
    use HasSortableTranslations;
    use Translatable;

    protected static ?string $model = Category::class;

    protected static ?string $recordTitleAttribute = 'slug';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

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

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.categories');
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
     * Builds the Filament form schema for the Category resource.
     *
     * Configures fields for category group selection, key, slug (title), locale-aware icon
     * upload/persistence, conditional color, is_active toggle, and position.
     *
     * @param  Schema  $schema  The base Filament form instance to configure.
     * @return Schema The configured Form containing the Category resource fields.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.resources.category.section_category'))
                    ->columns(1)
                    ->schema([
                        Select::make('category_group_id')
                            ->label(__('filament.resources.category.group'))
                            ->options(fn () => CategoryGroup::query()
                                ->orderBy('position')
                                ->get()
                                ->mapWithKeys(fn ($group) => [
                                    $group->id => $group->getTranslation('title', app()->getLocale())
                                        ?: $group->getTranslation('title', 'de'),
                                ])
                                ->toArray())
                            ->default(fn () => request()->query('category_group_id'))
                            ->required()
                            ->searchable()
                            ->live(),
                        TextInput::make('key')
                            ->label(__('filament.resources.category.key'))
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(),
                        TextInput::make('slug')
                            ->label(__('filament.resources.category.title'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, $record): void {
                                if ($record === null && filled($state)) {
                                    $set('key', Str::slug($state));
                                }
                            }),
                        FileUpload::make('icon')
                            ->label(__('filament.resources.category.icon'))
                            ->disk('public')
                            ->directory('categories')
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'])
                            ->maxSize(1024) // 1 MB
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
                                $path = is_array($state) ? (array_values($state)[0] ?? null) : $state;

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
                        ColorPicker::make('color')
                            ->label(__('filament.resources.category.color'))
                            ->required(false)
                            ->hex()
                            ->visible(fn (Get $get): bool => app(BrandingSettings::class)->tile_color_source_group_id !== null
                                && (int) $get('category_group_id') === app(BrandingSettings::class)->tile_color_source_group_id),
                        Toggle::make('is_active')
                            ->label(__('filament.resources.category.is_active'))
                            ->default(true),
                    ]),
            ]);
    }

    /**
     * Configure the resource table's columns, filters, row actions, and bulk actions.
     *
     * @param  Table  $table  The table to configure.
     * @return Table The configured table instance.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')
                    ->label(__('filament.resources.category.title'))
                    ->formatStateUsing(function ($state, Category $record, $livewire) {
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
                    }),
                TextColumn::make('group.title')
                    ->label(__('filament.resources.category.group'))
                    ->formatStateUsing(function ($state, $record, $livewire) {
                        $locale = $livewire->activeLocale ?? app()->getLocale();

                        return $state ?? $record->group?->getTranslation('title', $locale);
                    })
                    ->sortable(query: function (Builder $query, string $direction, $livewire) {
                        $locale = $livewire->activeLocale ?? app()->getLocale();
                        $expression = static::getSortableTranslationExpression('category_groups.title', $locale);
                        $query->select('categories.*')
                            ->leftJoin('category_groups', 'categories.category_group_id', '=', 'category_groups.id')
                            ->orderByRaw("{$expression} {$direction}");
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('key')
                    ->label(__('filament.resources.category.key'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('icon')
                    ->label(__('filament.resources.category.icon'))
                    ->disk('public')
                    ->square()
                    ->size(40)
                    // An icon-only cell would be a record link without a name.
                    ->disabledClick()
                    ->getStateUsing(function (Category $record, $livewire) {
                        $icon = $record->getRawOriginal('icon');
                        if (! $icon) {
                            return null;
                        }
                        $decoded = json_decode($icon, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $locale = $livewire->activeLocale ?? app()->getLocale();

                            return $decoded[$locale] ?? $decoded['de'] ?? $decoded['en'] ?? null;
                        }

                        return $icon;
                    }),
                ColorColumn::make('color')
                    ->label(__('filament.resources.category.color'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')
                    ->label(__('filament.resources.category.is_active'))
                    ->sortable(),
                TextColumn::make('position')
                    ->label(__('filament.resources.category.position'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament.resources.category.created_at'))
                    ->dateTime(__('filament.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('filament.resources.category.updated_at'))
                    ->dateTime(__('filament.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('filament.resources.category.is_active')),
                SelectFilter::make('category_group_id')
                    ->label(__('filament.resources.category.group'))
                    ->options(function () {
                        $locale = app()->getLocale();

                        return CategoryGroup::query()
                            ->orderBy('position')
                            ->get()
                            ->mapWithKeys(fn ($group) => [
                                $group->id => $group->getTranslation('title', $locale, false)
                                    ?: $group->getTranslation('title', 'de', false),
                            ]);
                    })
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->url(fn (Category $record, $livewire) => static::getUrl('edit', [
                        'record' => $record,
                        'activeLocale' => $livewire->activeLocale ?? app()->getLocale(),
                    ])),
                DeleteAction::make(),
            ])
            ->defaultSort('position')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
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
