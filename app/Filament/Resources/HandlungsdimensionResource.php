<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HandlungsdimensionResource\Pages;
use App\Filament\Resources\HandlungsdimensionResource\RelationManagers;
use App\Models\Handlungsdimension;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class HandlungsdimensionResource extends Resource
{
    use Translatable;

    protected static ?string $model = Handlungsdimension::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Kategorien';
    protected static ?int $navigationSort = 11;

    /**
     * Get the navigation label shown for this resource.
     *
     * @return string The translated navigation label for the resource.
     */
    public static function getNavigationLabel(): string
    {
        return __('filament.resources.handlungsdimension.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.handlungsdimension.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.handlungsdimension.plural_model_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('key')
                    ->label(__('filament.resources.handlungsdimension.key'))
                    ->required()
                    ->maxLength(255)
                    ->disabled(fn ($record) => $record !== null), // Key cannot be changed after creation
                Forms\Components\TextInput::make('title')
                    ->label(__('filament.resources.handlungsdimension.title'))
                    ->required(),
                Forms\Components\FileUpload::make('icon')
                    ->label(__('filament.resources.handlungsdimension.icon'))
                    ->disk('public')
                    ->directory('handlungsdimensionen')
                    ->image()
                    ->preserveFilenames()
                    ->required(false),
                Forms\Components\ColorPicker::make('color')
                    ->label(__('filament.resources.handlungsdimension.color'))
                    ->required(false)
                    ->hex(),
                Forms\Components\Select::make('handlungsfelder')
                    ->label(__('filament.resources.handlungsdimension.handlungsfelder'))
                    ->relationship('handlungsfelder', 'slug')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('slug', app()->getLocale()))
                    ->preload()
                    ->multiple(),
                Forms\Components\TextInput::make('position')
                    ->label(__('filament.resources.handlungsdimension.position'))
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label(__('filament.resources.handlungsdimension.key'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->label(__('filament.resources.handlungsdimension.title'))
                    ->searchable(),
                Tables\Columns\ImageColumn::make('icon')
                    ->label(__('filament.resources.handlungsdimension.icon'))
                    ->disk('public')
                    ->square()
                    ->size(40),
                Tables\Columns\ColorColumn::make('color')
                    ->label(__('filament.resources.handlungsdimension.color'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('position')
                    ->label(__('filament.resources.handlungsdimension.position'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.resources.handlungsdimension.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.resources.handlungsdimension.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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

    /**
     * Define the resource pages and their route paths.
     *
     * Returns an associative array that maps page identifiers to their route builders.
     * The array contains the keys `index`, `create`, and `edit` with their respective route paths.
     *
     * @return array<string, \Closure|string> Mapping of page keys to route definitions.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHandlungsdimensions::route('/'),
            'create' => Pages\CreateHandlungsdimension::route('/create'),
            'edit' => Pages\EditHandlungsdimension::route('/{record}/edit'),
        ];
    }

    /**
     * Controls whether this resource is registered in the Filament navigation.
     *
     * @return bool `true` if the resource should be shown in navigation, `false` otherwise.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}