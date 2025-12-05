<?php

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class ListBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('list')
            ->label('Liste')
            ->icon('heroicon-o-list-bullet')
            ->schema([
                Repeater::make('items')
                    ->label('Listeneinträge')
                    ->schema([
                        TextInput::make('text')
                            ->label('Text')
                            ->required()
                    ])
                    ->defaultItems(1)
                    ->collapsible(),
                Radio::make('list_type')
                    ->label('Listentyp')
                    ->options([
                        'bullet' => 'Aufzählung',
                        'numbered' => 'Nummeriert',
                    ])
                    ->default('bullet')
                    ->required(),
            ]);
    }
}





