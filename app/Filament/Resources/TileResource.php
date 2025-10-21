<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TileResource\Pages;
use App\Filament\Resources\TileResource\RelationManagers;
use App\Models\Tile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use Filament\Resources\Concerns\Translatable;

class TileResource extends Resource
{
    use Translatable;

    protected static ?string $model = Tile::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    
    public static function getNavigationLabel(): string
    {
        return __('filament.resources.tile.navigation_label');
    }
    
    public static function getModelLabel(): string
    {
        return __('filament.resources.tile.model_label');
    }
    
    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.tile.plural_model_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make(__('filament.tabs.contents'))
                    ->tabs([
                        // Tab 1: Kachel
                        Tabs\Tab::make(__('filament.tabs.tile'))
                            ->schema([
                                Select::make('categories')
                                    ->label(__('filament.resources.tile.categories'))
                                    ->relationship('categories', 'slug')
                                    ->preload()
                                    ->multiple(),
                                TextInput::make('title')
                                    ->label(__('filament.resources.tile.title'))
                                    ->required(),
                                RichEditor::make('description')
                                    ->label(__('filament.resources.tile.description')),
                                FileUpload::make('icon')
                                    ->label(__('filament.resources.tile.icon'))
                                    ->disk('public')
                                    ->directory('tiles')
                                    ->preserveFilenames()
                                    ->required(false),
                                TextInput::make('position')
                                    ->label(__('filament.resources.tile.position'))
                                    ->numeric()
                                    ->default(0),
                            ]),

                        // Tab 2: Hintergrundseite
                        Tabs\Tab::make(__('filament.tabs.background_page'))
                            ->schema([
                                Section::make(__('filament.sections.background'))
                                    ->relationship('backgroundPage')
                                    ->schema([
                                        TextInput::make('slug')
                                            ->label(__('filament.resources.tile.slug'))
                                            ->unique(ignoreRecord: true),
                                        RichEditor::make('content')
                                            ->label(__('filament.resources.tile.content')),
                                        TextInput::make('position')
                                            ->label(__('filament.resources.tile.position'))
                                            ->numeric()
                                            ->default(0),
                                    ]),
                            ]),

                        // Tab 3: Kennzahlen
                        Tabs\Tab::make(__('filament.tabs.metrics'))
                            ->schema([
                                Repeater::make('tileYears')
                                    ->relationship('tileYears')
                                    ->label(__('filament.resources.tile.year_groups'))
                                    ->defaultItems(0)            // 0 leere Einträge erzeugen
                                    ->addActionLabel(__('filament.resources.tile.add_year_group'))
                                    ->schema([
                                        TextInput::make('year')
                                            ->label(__('filament.resources.tile.year'))
                                            ->numeric(),
                                        Repeater::make('metrics')
                                            ->relationship('metrics')
                                            ->label(__('filament.resources.tile.metrics_label'))
                                            ->schema([
                                                TextInput::make('label')
                                                    ->label(__('filament.resources.tile.label')),
                                                TextInput::make('value')
                                                    ->label(__('filament.resources.tile.value'))
                                                    ->numeric(),
                                                TextInput::make('unit')
                                                    ->label(__('filament.resources.tile.unit')),
                                                FileUpload::make('icon')
                                                    ->label(__('filament.resources.tile.icon'))
                                                    ->disk('public')
                                                    ->directory('metrics')
                                                    ->image()
                                                    ->preserveFilenames()
                                                    ->required(false),
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
                    ->label(__('filament.resources.tile.title'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('icon')
                    ->label(__('filament.resources.tile.icon'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('position')
                    ->label(__('filament.resources.tile.position'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.resources.tile.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.resources.tile.updated_at'))
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
