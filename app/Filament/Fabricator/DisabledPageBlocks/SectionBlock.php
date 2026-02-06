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
            ->label('Section')
            ->icon('heroicon-o-rectangle-stack')
            ->schema([
                TextInput::make('title')
                    ->label('Titel')
                    ->maxLength(255),
                ColorPicker::make('background_color')
                    ->label('Hintergrundfarbe')
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
