<?php

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class IntroTextBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('intro-text')
            ->label('Intro Text')
            ->icon('heroicon-o-document-text')
            ->schema([
                TextInput::make('heading')
                    ->label('Überschrift')
                    ->required()
                    ->maxLength(255),
                RichEditor::make('text')
                    ->label('Text')
                    ->required()
                    ->toolbarButtons([
                        'bold',
                        'italic',
                        'link',
                        'bulletList',
                        'orderedList',
                    ]),
            ]);
    }
}

