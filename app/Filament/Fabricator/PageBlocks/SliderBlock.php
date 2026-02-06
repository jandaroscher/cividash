<?php

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Concerns\HasBlockActiveToggleAction;
use App\Filament\Support\RichEditorConfig;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class SliderBlock extends PageBlock
{
    use HasBlockActiveToggleAction;

    /**
     * Create the Block configuration for the slider page block.
     *
     * The returned Block defines a "slider" with a Repeater named "items" containing
     * fields: `title`, `description` (rich editor), `image` (image upload, stored on
     * the `public` disk under `slider-images` with image editor enabled), `link_url`,
     * `link_text`, and a hidden `is_active` flag. The repeater provides an extra item
     * action for toggling item active state, uses the item's `title` as its label,
     * defaults to one item, and is collapsible. Both item-level and block-level
     * `is_active` fields default to `true` when hydrated if unset.
     *
     * @return Block The configured slider Block instance.
     */
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
                        RichEditorConfig::make('description')
                            ->label('Beschreibung'),
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
