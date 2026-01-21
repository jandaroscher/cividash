<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SDGZielResource\Pages;
use App\Filament\Resources\SDGZielResource\RelationManagers;
use App\Models\SDGZiel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SDGZielResource extends Resource
{
    use Translatable;

    protected static ?string $model = SDGZiel::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationGroup = 'Kategorien';
    protected static ?int $navigationSort = 12;

    /**
     * Get the navigation label for the resource.
     *
     * @return string The translated navigation label for the resource.
     */
    public static function getNavigationLabel(): string
    {
        return __('filament.resources.sdg_ziel.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.sdg_ziel.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.sdg_ziel.plural_model_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('number')
                    ->label(__('filament.resources.sdg_ziel.number'))
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(17)
                    ->disabled(fn ($record) => $record !== null), // Number cannot be changed after creation
                Forms\Components\TextInput::make('title')
                    ->label(__('filament.resources.sdg_ziel.title'))
                    ->required(),
                Forms\Components\FileUpload::make('icon')
                    ->label(__('filament.resources.sdg_ziel.icon'))
                    ->disk('public')
                    ->directory('sdg-ziele')
                    ->image()
                    ->preserveFilenames()
                    ->required(false),
                Forms\Components\TextInput::make('position')
                    ->label(__('filament.resources.sdg_ziel.position'))
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label(__('filament.resources.sdg_ziel.number'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label(__('filament.resources.sdg_ziel.title'))
                    ->searchable(),
                Tables\Columns\ImageColumn::make('icon')
                    ->label(__('filament.resources.sdg_ziel.icon'))
                    ->disk('public')
                    ->square()
                    ->size(40),
                Tables\Columns\TextColumn::make('position')
                    ->label(__('filament.resources.sdg_ziel.position'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.resources.sdg_ziel.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.resources.sdg_ziel.updated_at'))
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
     * Define the resource pages and their route bindings.
     *
     * @return array<string,mixed> Map of page identifiers (e.g. 'index', 'create', 'edit') to their route handlers.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSDGZiels::route('/'),
            'create' => Pages\CreateSDGZiel::route('/create'),
            'edit' => Pages\EditSDGZiel::route('/{record}/edit'),
        ];
    }

    /**
     * Determine whether the resource should be registered in Filament's navigation.
     *
     * @return bool `true` if the resource should be shown in the navigation, `false` otherwise.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}