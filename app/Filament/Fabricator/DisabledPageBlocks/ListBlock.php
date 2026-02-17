<?php

namespace App\Filament\Fabricator\DisabledPageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class ListBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('list')
            ->label(__('filament.blocks.list.label'))
            ->icon('heroicon-o-list-bullet')
            ->schema([
                Repeater::make('items')
                    ->label(__('filament.blocks.list.items'))
                    ->schema([
                        TextInput::make('text')
                            ->label(__('filament.blocks.list.text'))
                            ->required(),
                    ])
                    ->defaultItems(1)
                    ->collapsible(),
                Radio::make('list_type')
                    ->label(__('filament.blocks.list.list_type'))
                    ->options([
                        'bullet' => __('filament.blocks.list.type_bullet'),
                        'numbered' => __('filament.blocks.list.type_numbered'),
                    ])
                    ->default('bullet')
                    ->required(),
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
