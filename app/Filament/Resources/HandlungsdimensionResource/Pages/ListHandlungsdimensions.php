<?php

namespace App\Filament\Resources\HandlungsdimensionResource\Pages;

use App\Filament\Resources\HandlungsdimensionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHandlungsdimensions extends ListRecords
{
    use ListRecords\Concerns\Translatable;

    protected static string $resource = HandlungsdimensionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
}
