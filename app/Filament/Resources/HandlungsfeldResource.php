<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HandlungsfeldResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HandlungsfeldResource extends Resource
{
    use Translatable;

    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationGroup = 'Kategorien';
    protected static ?int $navigationSort = 10;

    /**
     * Get the resource's navigation label in the current locale.
     *
     * @return string The navigation label translated for the current locale.
     */
    public static function getNavigationLabel(): string
    {
        return __('filament.resources.handlungsfeld.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.handlungsfeld.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.handlungsfeld.plural_model_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('slug')
                    ->label(__('filament.resources.handlungsfeld.title'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\FileUpload::make('icon')
                    ->label(__('filament.resources.handlungsfeld.icon'))
                    ->disk('public')
                    ->directory('handlungsfelder')
                    ->image()
                    ->preserveFilenames()
                    ->required(false),
                Forms\Components\TextInput::make('position')
                    ->label(__('filament.resources.handlungsfeld.position'))
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('filament.resources.handlungsfeld.title'))
                    ->searchable(),
                Tables\Columns\ImageColumn::make('icon')
                    ->label(__('filament.resources.handlungsfeld.icon'))
                    ->disk('public')
                    ->square()
                    ->size(40),
                Tables\Columns\TextColumn::make('position')
                    ->label(__('filament.resources.handlungsfeld.position'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.resources.handlungsfeld.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.resources.handlungsfeld.updated_at'))
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHandlungsfelder::route('/'),
            'create' => Pages\CreateHandlungsfeld::route('/create'),
            'edit' => Pages\EditHandlungsfeld::route('/{record}/edit'),
        ];
    }
}
