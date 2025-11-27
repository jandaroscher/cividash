<?php

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class FilterBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('filter')
            ->label('Filter')
            ->icon('heroicon-o-funnel')
            ->schema([
                TextInput::make('label')
                    ->label('Label')
                    ->maxLength(255)
                    ->default('Filter'),
                Checkbox::make('show_categories')
                    ->label('Kategorien anzeigen')
                    ->default(true),
                Checkbox::make('show_search')
                    ->label('Suchfeld anzeigen')
                    ->default(true),
            ]);
    }
}



