<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TileResource\Pages;
use App\Filament\Resources\TileResource\RelationManagers;
use App\Models\Tile;
use Filament\Forms;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TileResource extends Resource
{
    protected static ?string $model = Tile::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Inhalte')
                    ->tabs([
                        // Tab 1: Kachel
                        Tabs\Tab::make('Kachel')
                            ->schema([
                                MultiSelect::make('categories')
                                    ->relationship('categories', 'slug')
                                    ->preload(),
                                TextInput::make('title')->required(),
                                RichEditor::make('description'),
                                TextInput::make('icon'),
                                TextInput::make('position')
                                    ->numeric()
                                    ->default(0),
                            ]),

                        // Tab 2: Hintergrundseite
                        Tabs\Tab::make('Hintergrundseite')
                            ->schema([
                                Section::make('Hintergrund')
                                    ->relationship('backgroundPage')
                                    ->schema([
                                        TextInput::make('slug')
                                            ->unique(ignoreRecord: true),
                                        RichEditor::make('content'),
                                        TextInput::make('position')
                                            ->numeric()
                                            ->default(0),
                                    ]),
                            ]),

                        // Tab 3: Kennzahlen
                        Tabs\Tab::make('Kennzahlen')
                            ->schema([
                                Repeater::make('tileYears')
                                    ->relationship('tileYears')
                                    ->label('Jahresgruppen')
                                    ->defaultItems(0)            // 0 leere Einträge erzeugen
                                    ->addActionLabel('Jahresgruppe hinzufügen')
                                    ->schema([
                                        TextInput::make('year')
                                            ->label('Jahr')
                                            ->numeric(),
                                        Repeater::make('metrics')
                                            ->relationship('metrics')
                                            ->label('Kennzahlen')
                                            ->schema([
                                                TextInput::make('label')
                                                    ->label('Bezeichnung'),
                                                TextInput::make('value')
                                                    ->label('Wert')
                                                    ->numeric(),
                                                TextInput::make('unit')
                                                    ->label('Einheit'),
                                                TextInput::make('icon')
                                                    ->label('Icon'),
                                            ])
                                            ->collapsible()
                                    ])
                                    ->collapsible()
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('icon')
                    ->searchable(),
                Tables\Columns\TextColumn::make('position')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
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
            'index' => Pages\ListTiles::route('/'),
            'create' => Pages\CreateTile::route('/create'),
            'edit' => Pages\EditTile::route('/{record}/edit'),
        ];
    }
}
