<?php

namespace App\Filament\Fabricator\DisabledPageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class LinkBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('link')
            ->label(__('filament.blocks.link.label'))
            ->icon('heroicon-o-link')
            ->schema([
                TextInput::make('text')
                    ->label(__('filament.blocks.link.text'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('url')
                    ->label(__('filament.blocks.link.url'))
                    ->url()
                    ->required(),
                Select::make('target')
                    ->label(__('filament.blocks.link.target'))
                    ->options([
                        '_self' => __('filament.blocks.link.target_self'),
                        '_blank' => __('filament.blocks.link.target_blank'),
                    ])
                    ->default('_self')
                    ->required(),
                Select::make('style')
                    ->label(__('filament.blocks.link.style'))
                    ->options([
                        'primary' => __('filament.blocks.link.style_primary'),
                        'secondary' => __('filament.blocks.link.style_secondary'),
                        'ghost' => __('filament.blocks.link.style_ghost'),
                    ])
                    ->default('primary')
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
