<?php

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Support\RichEditorConfig;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class FAQBlock extends PageBlock
{
    /**
     * Build the Block schema for an FAQ content block.
     *
     * @return Block A configured Block with a labeled FAQ icon, a repeater of `items` (each with `question` and `answer` fields), and a hidden `is_active` flag defaulting to `true`.
     */
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
                        RichEditorConfig::make('answer')
                            ->label('Antwort')
                            ->required(),
                    ])
                    ->defaultItems(1)
                    ->collapsible(),
                Hidden::make('is_active')
                    ->default(true)
                    ->afterStateHydrated(function (Hidden $component, $state): void {
                        if ($state === null) {
                            $component->state(true);
                        }
                    }),
            ]);
    }
}
