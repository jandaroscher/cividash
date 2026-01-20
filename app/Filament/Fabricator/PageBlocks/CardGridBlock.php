<?php

namespace App\Filament\Fabricator\PageBlocks;

use App\Models\Tile;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class CardGridBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('card-grid')
            ->label('Tile Grid')
            ->icon('heroicon-o-rectangle-stack')
            ->schema([
                Select::make('tiles')
                    ->label('Tiles')
                    ->multiple()
                    ->options(function () {
                        return Tile::query()
                            ->orderBy('position')
                            ->get()
                            ->mapWithKeys(function ($tile) {
                                return [$tile->id => $tile->getTranslation('title', app()->getLocale())];
                            });
                    })
                    ->searchable()
                    ->preload()
                    ->helperText('Leer lassen, um alle Tiles anzuzeigen. Auswählen, um nur bestimmte Tiles anzuzeigen.'),
                Hidden::make('is_active')
                    ->default(true)
                    ->afterStateHydrated(function (Hidden $component, $state): void {
                        if ($state === null) {
                            $component->state(true);
                        }
                    }),
            ]);
    }

    public static function mutateData(array $data): array
    {
        // Ensure tiles is always an array
        if (!isset($data['tiles']) || empty($data['tiles'])) {
            // If no tiles selected, get all tiles
            $data['tiles'] = Tile::query()
                ->orderBy('position')
                ->pluck('id')
                ->toArray();
        } else {
            // Ensure it's an array
            $data['tiles'] = is_array($data['tiles']) ? $data['tiles'] : [$data['tiles']];
        }

        // Don't preload translated tile data here - translations should happen at render time
        // to ensure the correct locale is used. Only store the tile IDs.

        return $data;
    }
}

