<?php

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class SliderBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('slider')
            ->label('Slider')
            ->icon('heroicon-o-arrows-right-left')
            ->schema([
                Repeater::make('items')
                    ->label('Slider Items')
                    ->schema([
                        TextInput::make('title')
                            ->label('Titel')
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Beschreibung')
                            ->rows(3),
                        FileUpload::make('image')
                            ->label('Bild')
                            ->image()
                            ->directory('slider-images')
                            ->disk('public')
                            ->imageEditor(),
                        TextInput::make('link_url')
                            ->label('Link URL')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('link_text')
                            ->label('Link Text')
                            ->maxLength(50),
                    ])
                    ->defaultItems(1)
                    ->collapsible(),
            ]);
    }
}





