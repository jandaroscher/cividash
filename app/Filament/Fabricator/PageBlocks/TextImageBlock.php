<?php

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Support\RichEditorConfig;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class TextImageBlock extends PageBlock
{
    /**
     * Create and return the Block definition for the "text-image" fabricator block.
     *
     * Schema includes a required rich text field `text`, an image upload `image` (stored in `text-images` on the `public` disk with an image editor and aspect ratios 4:3, 16:9, 1:1), a text `image_alt` (max length 255) for accessibility, a required radio `image_position` with options `left` (default) and `right`, and a hidden `is_active` field that defaults to `true` when not provided.
     *
     * @return Block The configured Block instance for the `text-image` block.
     */
    public static function getBlockSchema(): Block
    {
        return Block::make('text-image')
            ->label('Text & Bild')
            ->icon('heroicon-o-photo')
            ->schema([
                RichEditorConfig::make('text')
                    ->label('Text')
                    ->required(),
                FileUpload::make('image')
                    ->label('Bild')
                    ->image()
                    ->directory('text-images')
                    ->disk('public')
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        '4:3',
                        '16:9',
                        '1:1',
                    ]),
                TextInput::make('image_alt')
                    ->label('Alt-Text für Bild')
                    ->helperText('Beschreibung des Bildes für Barrierefreiheit (WCAG)')
                    ->maxLength(255),
                Radio::make('image_position')
                    ->label('Bildposition')
                    ->options([
                        'left' => 'Links',
                        'right' => 'Rechts',
                    ])
                    ->default('left')
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
