<?php

namespace App\Filament\Fabricator\DisabledPageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class TileAppBlock extends PageBlock
{
    /**
     * Builds the Block schema for the "tile-app" page block.
     *
     * The returned Block is named 'tile-app', labeled 'Kacheln', uses the
     * 'heroicon-o-squares-2x2' icon, and contains two toggles in its schema:
     * - `show_search`: label "Suche anzeigen", default `true`
     * - `show_filter`: label "Filter anzeigen", default `true`
     *
     * @return Block The configured Block instance for the tile-app page block.
     */
    public static function getBlockSchema(): Block
    {
        return Block::make('tile-app')
            ->label(__('filament.blocks.tile_app.label'))
            ->icon('heroicon-o-squares-2x2')
            ->schema([
                Toggle::make('show_search')
                    ->label(__('filament.blocks.tile_app.show_search'))
                    ->default(true),
                Toggle::make('show_filter')
                    ->label(__('filament.blocks.tile_app.show_filter'))
                    ->default(true),
                Hidden::make('is_active')
                    ->default(true)
                    ->afterStateHydrated(function (Hidden $component, $state): void {
                        if ($state === null) {
                            $component->state(true);
                        }
                    }),
            ]);
    }

    /**
     * Normalize and ensure boolean values for the 'show_search' and 'show_filter' keys in the given data array.
     *
     * Existing values for these keys are cast to `bool`; if a key is missing it is added with a value of `true`.
     *
     * @param  array  $data  Input data array that may contain 'show_search' and/or 'show_filter'.
     * @return array The modified data array with 'show_search' and 'show_filter' guaranteed to be booleans.
     */
    public static function mutateData(array $data): array
    {
        // Ensure boolean values are properly set, defaulting to true if not present
        // Use array_key_exists to check if key exists, even if value is false
        if (array_key_exists('show_search', $data)) {
            $data['show_search'] = (bool) $data['show_search'];
        } else {
            $data['show_search'] = true;
        }

        if (array_key_exists('show_filter', $data)) {
            $data['show_filter'] = (bool) $data['show_filter'];
        } else {
            $data['show_filter'] = true;
        }

        return $data;
    }
}
