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
            ->label(__('filament.blocks.faq.label'))
            ->icon('heroicon-o-question-mark-circle')
            ->schema([
                Repeater::make('items')
                    ->label(__('filament.blocks.faq.items'))
                    ->addActionLabel(__('filament.actions.add_to_faq_items'))
                    ->schema([
                        TextInput::make('question')
                            ->label(__('filament.blocks.faq.question'))
                            ->required()
                            ->maxLength(255),
                        RichEditorConfig::make('answer')
                            ->label(__('filament.blocks.faq.answer'))
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
