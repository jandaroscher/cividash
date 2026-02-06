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
            ->label('Hero Section')
            ->icon('heroicon-o-photo')
            ->schema([
                TextInput::make('title')
                    ->label('Titel')
                    ->required()
                    ->maxLength(255),
                Textarea::make('subtitle')
                    ->label('Untertitel')
                    ->rows(3),
                FileUpload::make('image')
                    ->label('Bild')
                    ->image()
                    ->directory('hero-images')
                    ->disk('public')
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        '16:9',
                        '21:9',
                    ]),
                TextInput::make('image_alt')
                    ->label('Image Alt Text')
                    ->helperText('Describe the image for screen reader users')
                    ->maxLength(255),
                TextInput::make('cta_text')
                    ->label('CTA Button Text')
                    ->maxLength(50),
                TextInput::make('cta_url')
                    ->label('CTA Button URL')
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
