<?php

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Support\ImageMetadataFields;
use App\Filament\Support\RichEditorConfig;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class IntroTextBlock extends PageBlock
{
    /**
     * Build the Filament builder Block schema for the "Intro Text" page block.
     *
     * The block schema includes:
     * - `heading`: required text (max 255)
     * - `subheading`: text (max 255)
     * - `text`: required rich text (uses shared RichEditorConfig)
     * - `image` and `image_secondary`: image uploads stored in `intro-images` on the `public` disk with image editor enabled and aspect ratios `4:3`, `16:9`, `1:1`
     * - `{image}_title`, `{image}_alt` and `{image}_copyright` for both images (see ImageMetadataFields)
     * - `is_active`: hidden boolean defaulting to `true`; if hydrated value is `null`, the state is set to `true`
     *
     * @return Block The configured block schema for the `intro-text` block.
     */
    public static function getBlockSchema(): Block
    {
        return Block::make('intro-text')
            ->label(__('filament.blocks.intro_text.label'))
            ->icon('heroicon-o-document-text')
            ->schema([
                TextInput::make('heading')
                    ->label(__('filament.blocks.intro_text.heading'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('subheading')
                    ->label(__('filament.blocks.intro_text.subheading'))
                    ->maxLength(255),
                RichEditorConfig::make('text')
                    ->label(__('filament.blocks.intro_text.text'))
                    ->required(),
                ImageMetadataFields::make(
                    FileUpload::make('image')
                        ->label(__('filament.blocks.intro_text.image'))
                        ->image()
                        ->directory('intro-images')
                        ->disk('public')
                        ->maxSize(5120), // 5 MB
                ),
                ImageMetadataFields::make(
                    FileUpload::make('image_secondary')
                        ->label(__('filament.blocks.intro_text.image_secondary'))
                        ->image()
                        ->directory('intro-images')
                        ->disk('public')
                        ->maxSize(5120), // 5 MB
                ),
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
