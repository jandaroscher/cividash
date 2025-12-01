<?php

namespace App\Filament\Resources\HandlungsfeldResource\Pages;

use App\Filament\Resources\HandlungsfeldResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHandlungsfelder extends ListRecords
{
    use ListRecords\Concerns\Translatable;

    protected static string $resource = HandlungsfeldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
}

