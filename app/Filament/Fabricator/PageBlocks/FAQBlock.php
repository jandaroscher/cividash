<?php

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class FAQBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('faq')
            ->label('FAQ')
            ->icon('heroicon-o-question-mark-circle')
            ->schema([
                Repeater::make('items')
                    ->label('FAQ-Einträge')
                    ->schema([
                        TextInput::make('question')
                            ->label('Frage')
                            ->required()
                            ->maxLength(255),
                        RichEditor::make('answer')
                            ->label('Antwort')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'link',
                                'bulletList',
                                'orderedList',
                            ]),
                    ])
                    ->defaultItems(1)
                    ->collapsible(),
            ]);
    }
}


