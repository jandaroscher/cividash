<?php

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class TileAppBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('tile-app')
            ->label('Tile App')
            ->icon('heroicon-o-squares-2x2')
            ->schema([
                Select::make('mode')
                    ->label('Modus')
                    ->options([
                        'explore' => 'Explore',
                        'compare' => 'Compare',
                    ])
                    ->default('explore')
                    ->required(),
                TextInput::make('initial_category')
                    ->label('Initiale Kategorie')
                    ->maxLength(255),
                Toggle::make('use_mock_data')
                    ->label('Mock-Daten im Preview verwenden')
                    ->default(false),
            ]);
    }
}





