<?php

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class IntroTextBlock extends PageBlock
{
    /**
     * Create the Filament builder block schema for the "Intro Text" page block.
     *
     * The returned Block is configured with form fields for:
     * - `heading` (required text, max 255)
     * - `subheading` (text, max 255)
     * - `text` (required rich text with a limited toolbar)
     * - `image` and `image_secondary` (image uploads stored in `intro-images` on the `public` disk, with image editor enabled and aspect ratios `4:3`, `16:9`, `1:1`)
     * - `image_alt` and `image_secondary_alt` (alt text fields, max 255, with helper text for accessibility)
     *
     * @return \Filament\Forms\Components\Builder\Block The configured block schema for the intro-text block.
     */
    public static function getBlockSchema(): Block
    {
        return Block::make('intro-text')
            ->label('Intro Text')
            ->icon('heroicon-o-document-text')
            ->schema([
                TextInput::make('heading')
                    ->label('Überschrift')
                    ->required()
                    ->maxLength(255),
                TextInput::make('subheading')
                    ->label('Unterüberschrift')
                    ->maxLength(255),
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
                    ->directory('intro-images')
                    ->disk('public')
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        '4:3',
                        '16:9',
                        '1:1',
                    ]),
                TextInput::make('image_alt')
                    ->label('Alt-Text für Bild')
                    ->helperText('Beschreibung des Bildes für Barrierefreiheit')
                    ->maxLength(255),
                FileUpload::make('image_secondary')
                    ->label('Zweites Bild (optional)')
                    ->image()
                    ->directory('intro-images')
                    ->disk('public')
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        '4:3',
                        '16:9',
                        '1:1',
                    ]),
                TextInput::make('image_secondary_alt')
                    ->label('Alt-Text für zweites Bild')
                    ->helperText('Beschreibung des zweiten Bildes für Barrierefreiheit')
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
