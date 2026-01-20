<?php

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class TextImageBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('text-image')
            ->label('Text & Bild')
            ->icon('heroicon-o-photo')
            ->schema([
                RichEditor::make('text')
                    ->label('Text')
                    ->required()
                    ->toolbarButtons([
                        'bold',
                        'italic',
                        'link',
                        'bulletList',
                        'orderedList',
                    ]),
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

