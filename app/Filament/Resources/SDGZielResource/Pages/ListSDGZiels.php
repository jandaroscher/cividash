<?php

namespace App\Filament\Resources\SDGZielResource\Pages;

use App\Filament\Resources\SDGZielResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSDGZiels extends ListRecords
{
    use ListRecords\Concerns\Translatable;

    protected static string $resource = SDGZielResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
}
