<?php

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Concerns\HasBlockActiveToggleAction;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class SliderBlock extends PageBlock
{
    use HasBlockActiveToggleAction;

    public static function getBlockSchema(): Block
    {
        return Block::make('slider')
            ->label('Slider')
            ->icon('heroicon-o-arrows-right-left')
            ->schema([
                Repeater::make('items')
                    ->label('Slider Items')
                    ->extraItemActions([
                        static::getBlockActiveToggleAction(),
                    ])
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
                        Hidden::make('is_active')
                            ->default(true)
                            ->afterStateHydrated(function (Hidden $component, $state): void {
                                if ($state === null) {
                                    $component->state(true);
                                }
                            }),
                    ])
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
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






