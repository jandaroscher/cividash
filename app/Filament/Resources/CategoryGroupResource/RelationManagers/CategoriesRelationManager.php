<?php

namespace App\Filament\Resources\CategoryGroupResource\RelationManagers;

use App\Filament\Resources\CategoryResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories';

    protected static ?string $recordTitleAttribute = 'id';

    /**
     * Builds and returns the form schema for creating and editing Category records related to a CategoryGroup.
     *
     * The form includes fields for `key`, required `slug`, `icon`, `color`, and `position`. The `icon` field accepts
     * images, preserves filenames, stores uploads on the `public` disk under `categories`, and normalizes/dehydrates
     * state to support existing locale-keyed JSON icon values as well as simple paths. The `color` field is shown only
     * when the owner CategoryGroup indicates it as the color source.
     *
     * @return \Filament\Forms\Form The configured form instance.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('key')
                    ->label(__('filament.resources.category_group.items.key'))
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->label(__('filament.resources.category_group.items.title'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\FileUpload::make('icon')
                    ->label(__('filament.resources.category_group.items.icon'))
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
                    ->label(__('filament.resources.category_group.items.color'))
                    ->required(false)
                    ->hex()
                    ->visible(fn (RelationManager $livewire): bool => (bool) $livewire->getOwnerRecord()?->is_color_source),
                Forms\Components\TextInput::make('position')
                    ->label(__('filament.resources.category_group.items.position'))
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    /**
     * Builds the table used to list and manage categories related to the current category group.
     *
     * The table is configured with columns for key, slug, icon, color, position, created_at, and updated_at;
     * a header action to create a new category (prefilling the current category group); a per-record edit action
     * and row URL that navigate to the CategoryResource edit page; and a bulk delete action.
     *
     * @return Table The configured table instance.
     */
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label(__('filament.resources.category_group.items.key'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('filament.resources.category_group.items.title'))
                    ->searchable(),
                Tables\Columns\ImageColumn::make('icon')
                    ->label(__('filament.resources.category_group.items.icon'))
                    ->disk('public')
                    ->square()
                    ->size(40),
                Tables\Columns\ColorColumn::make('color')
                    ->label(__('filament.resources.category_group.items.color'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('position')
                    ->label(__('filament.resources.category_group.items.position'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.resources.category_group.items.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.resources.category_group.items.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->actions([
                Action::make('edit_child')
                    ->label(__('filament.actions.edit'))
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn ($record) => CategoryResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}