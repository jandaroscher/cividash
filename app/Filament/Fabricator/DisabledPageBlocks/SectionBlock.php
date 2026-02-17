<?php

namespace App\Filament\Fabricator\DisabledPageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class SectionBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('section')
            ->label(__('filament.blocks.section.label'))
            ->icon('heroicon-o-rectangle-stack')
            ->schema([
                TextInput::make('title')
                    ->label(__('filament.blocks.section.title'))
                    ->maxLength(255),
                ColorPicker::make('background_color')
                    ->label(__('filament.blocks.section.background_color'))
                    ->default('#ffffff'),
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
