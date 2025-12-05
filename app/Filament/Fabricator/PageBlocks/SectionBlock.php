<?php

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class SectionBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('section')
            ->label('Section')
            ->icon('heroicon-o-rectangle-stack')
            ->schema([
                TextInput::make('title')
                    ->label('Titel')
                    ->maxLength(255),
                ColorPicker::make('background_color')
                    ->label('Hintergrundfarbe')
                    ->default('#ffffff'),
            ]);
    }
}





