<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Resources\CategoryGroupResource\RelationManagers;

use App\Filament\Concerns\HasSortableTranslations;
use App\Filament\Resources\CategoryResource;
use App\Filament\Support\MediaPicker;
use App\Models\Category;
use App\Services\Media\MediaLibrary;
use App\Settings\BrandingSettings;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CategoriesRelationManager extends RelationManager
{
    use HasSortableTranslations;

    protected static string $relationship = 'categories';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.resources.category.plural_model_label');
    }

    protected static function getModelLabel(): ?string
    {
        return __('filament.resources.category.model_label');
    }

    protected static function getPluralModelLabel(): ?string
    {
        return __('filament.resources.category.plural_model_label');
    }

    /**
     * Builds the Filament form schema for creating and editing Category records related to the current CategoryGroup.
     *
     * The form defines inputs for `key`, `slug`, `icon` (image upload stored on the `public` disk under `categories`, supporting per-locale JSON or plain path values), `color` (visible only when the owner CategoryGroup is the color source), `is_active`, and numeric `position`.
     *
     * @return Schema The configured form instance.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->label(__('filament.resources.category_group.items.key'))
                    ->maxLength(255)
                    ->nullable()
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('slug')
                    ->label(__('filament.resources.category_group.items.title'))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set, $record): void {
                        if ($record === null && filled($state)) {
                            $set('key', Str::slug($state));
                        }
                    }),
                MediaPicker::make('icon')
                    ->label(__('filament.resources.category_group.items.icon'))
                    ->acceptedFileTypes(['image/*'])
                    ->maxSize(1024) // 1 MB
                    ->localized(),
                ColorPicker::make('color')
                    ->label(__('filament.resources.category_group.items.color'))
                    ->required(false)
                    ->hex()
                    ->visible(fn (RelationManager $livewire): bool => app(BrandingSettings::class)->tile_color_source_group_id === $livewire->getOwnerRecord()?->id),
                Toggle::make('is_active')
                    ->label(__('filament.resources.category_group.items.is_active'))
                    ->default(true),
            ]);
    }

    /**
     * Configure the Filament table used to manage Category records related to the current CategoryGroup.
     *
     * @return Table The configured table instance.
     */
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label(__('filament.resources.category_group.items.key'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('filament.resources.category_group.items.title'))
                    ->formatStateUsing(function ($state, Category $record) {
                        return $record->getTranslation('slug', app()->getLocale(), false)
                            ?: $record->getTranslation('slug', 'de', false)
                            ?: $state;
                    })
                    ->searchable(query: static::getSearchableTranslationClosure('slug'))
                    ->sortable(query: function (Builder $query, string $direction) {
                        $expression = static::getSortableTranslationExpression('slug', app()->getLocale());
                        $query->orderByRaw("{$expression} {$direction}");
                    }),
                ImageColumn::make('icon')
                    ->label(__('filament.resources.category_group.items.icon'))
                    ->disk('public')
                    ->square()
                    ->size(40)
                    ->getStateUsing(function (Category $record, $livewire = null) {
                        $icon = $record->getRawOriginal('icon');
                        if (! $icon) {
                            return null;
                        }
                        $decoded = json_decode($icon, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $locale = $livewire?->activeLocale ?? app()->getLocale();

                            return app(MediaLibrary::class)->path($decoded[$locale] ?? $decoded['de'] ?? $decoded['en'] ?? null);
                        }

                        return app(MediaLibrary::class)->path($icon);
                    }),
                ColorColumn::make('color')
                    ->label(__('filament.resources.category_group.items.color'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')
                    ->label(__('filament.resources.category_group.items.is_active'))
                    ->sortable(),
                TextColumn::make('position')
                    ->label(__('filament.resources.category_group.items.position'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament.resources.category_group.items.created_at'))
                    ->dateTime(__('filament.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('filament.resources.category_group.items.updated_at'))
                    ->dateTime(__('filament.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('filament.resources.category_group.items.is_active')),
            ])
            ->headerActions([
                Action::make('create_child')
                    ->label(__('filament.actions.create'))
                    ->icon('heroicon-o-plus')
                    ->url(fn (RelationManager $livewire) => CategoryResource::getUrl('create', [
                        'category_group_id' => $livewire->getOwnerRecord()?->id,
                    ])),
            ])
            ->recordUrl(fn ($record) => CategoryResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                Action::make('edit_child')
                    ->label(__('filament.actions.edit'))
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn ($record) => CategoryResource::getUrl('edit', ['record' => $record])),
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
}
