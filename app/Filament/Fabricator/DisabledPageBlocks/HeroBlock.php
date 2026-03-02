<?php

namespace App\Filament\Fabricator\DisabledPageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class HeroBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('hero')
            ->label(__('filament.blocks.hero.label'))
            ->icon('heroicon-o-photo')
            ->schema([
                TextInput::make('title')
                    ->label(__('filament.blocks.hero.title'))
                    ->required()
                    ->maxLength(255),
                Textarea::make('subtitle')
                    ->label(__('filament.blocks.hero.subtitle'))
                    ->rows(3),
                FileUpload::make('image')
                    ->label(__('filament.blocks.hero.image'))
                    ->image()
                    ->directory('hero-images')
                    ->disk('public'),
                TextInput::make('image_alt')
                    ->label(__('filament.blocks.hero.image_alt'))
                    ->helperText(__('filament.blocks.hero.image_alt_helper'))
                    ->maxLength(255),
                TextInput::make('cta_text')
                    ->label(__('filament.blocks.hero.cta_text'))
                    ->maxLength(50),
                TextInput::make('cta_url')
                    ->label(__('filament.blocks.hero.cta_url'))
                    ->url()
                    ->maxLength(255),
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
