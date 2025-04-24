<?php

namespace App\Filament\Resources\TileResource\Pages;

use App\Filament\Resources\TileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTiles extends ListRecords
{
    use ListRecords\Concerns\Translatable;

    protected static string $resource = TileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
}
