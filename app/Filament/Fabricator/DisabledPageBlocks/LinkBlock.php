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
            ->label('Link')
            ->icon('heroicon-o-link')
            ->schema([
                TextInput::make('text')
                    ->label('Link-Text')
                    ->required()
                    ->maxLength(255),
                TextInput::make('url')
                    ->label('URL')
                    ->url()
                    ->required(),
                Select::make('target')
                    ->label('Ziel')
                    ->options([
                        '_self' => 'Gleicher Tab',
                        '_blank' => 'Neuer Tab',
                    ])
                    ->default('_self')
                    ->required(),
                Select::make('style')
                    ->label('Stil')
                    ->options([
                        'primary' => 'Primär',
                        'secondary' => 'Sekundär',
                        'ghost' => 'Ghost',
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





