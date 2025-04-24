<?php

namespace App\Filament\Resources\TileResource\Pages;

use App\Filament\Resources\TileResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\ListRecords;

class CreateTile extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;

    protected static string $resource = TileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }
}
