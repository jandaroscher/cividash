<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TileResource\Pages;
use App\Filament\Resources\TileResource\RelationManagers;
use App\Models\Tile;
use Filament\Forms\Components\Builder;
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
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

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

    /**
     * Configure the form schema for the Tile resource with three tabs: Tile (basic fields and relationships), Background Page (block builder), and Metrics (nested repeaters for year groups and metrics).
     *
     * @param Form $form The form instance to configure.
     * @return Form The configured form containing the Tabs schema, a Builder for background blocks, and nested Repeaters for tile years and metrics.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make(__('filament.tabs.contents'))
                    ->tabs([
                        // Tab 1: Kachel
                        Tabs\Tab::make(__('filament.tabs.tile'))
                            ->schema([
                                Select::make('handlungsfelder')
                                    ->label(__('filament.resources.tile.handlungsfelder'))
                                    ->relationship('handlungsfelder', 'slug')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('slug', app()->getLocale()))
                                    ->preload()
                                    ->multiple(),
                                Select::make('handlungsdimension_id')
                                    ->label(__('filament.resources.tile.handlungsdimension'))
                                    ->relationship('handlungsdimension', 'title')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('title', app()->getLocale()))
                                    ->preload()
                                    ->searchable(),
                                Select::make('sdgZiele')
                                    ->label(__('filament.resources.tile.sdg_ziele'))
                                    ->relationship('sdgZiele', 'title')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('title', app()->getLocale()))
                                    ->preload()
                                    ->multiple()
                                    ->searchable(),
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
                                Builder::make('background_blocks')
                                    ->label(__('filament.resources.tile.background_blocks'))
                                    ->blocks(static::getBackgroundBlockSchemas())
                                    ->collapsible()
                                    ->collapsed(false),
                            ]),

                        // Tab 3: Kennzahlen
                        Tabs\Tab::make(__('filament.tabs.metrics'))
                            ->schema([
                                Repeater::make('tileYears')
                                    ->relationship('tileYears')
                                    ->label(__('filament.resources.tile.year_groups'))
                                    ->defaultItems(0)            // 0 leere Einträge erzeugen
                                    ->addActionLabel(__('filament.resources.tile.add_year_group'))
                                    ->itemLabel(fn(array $state): ?string => $state['year'] ?? null)
                                    ->schema([
                                        TextInput::make('year')
                                            ->label(__('filament.resources.tile.year'))
                                            ->numeric(),
                                        Repeater::make('metrics')
                                            ->relationship('metrics')
                                            ->label(__('filament.resources.tile.metrics_label'))
                                            ->itemLabel(fn(array $state): ?string => $state['label'] ?? null)
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
                Tables\Columns\ImageColumn::make('icon')
                    ->label(__('filament.resources.tile.icon'))
                    ->disk('public')
                    ->square()
                    ->size(40),
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

    /**
     * Register the resource's pages and their routes.
     *
     * @return array<string, mixed> Associative array mapping page identifiers ('index', 'create', 'edit') to their routed page classes.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTiles::route('/'),
            'create' => Pages\CreateTile::route('/create'),
            'edit' => Pages\EditTile::route('/{record}/edit'),
        ];
    }

    /**
         * Load and return Builder block schemas registered via the Fabricator configuration.
         *
         * Reads the `filament-fabricator.page-blocks.register` config, calls `getBlockSchema()` on each valid block class,
         * and returns the collected Builder block schemas.
         *
         * @return array<\Filament\Forms\Components\Builder\Block> The array of Builder block schemas to use in the background blocks.
         */
    protected static function getBackgroundBlockSchemas(): array
    {
        $blocks = [];
        $registeredBlocks = config('filament-fabricator.page-blocks.register', []);

        foreach ($registeredBlocks as $blockClass) {
            if (class_exists($blockClass) && method_exists($blockClass, 'getBlockSchema')) {
                $blocks[] = $blockClass::getBlockSchema();
            }
        }

        return $blocks;
    }
}