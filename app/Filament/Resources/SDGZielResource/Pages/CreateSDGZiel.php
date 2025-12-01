<?php

namespace App\Filament\Resources\SDGZielResource\Pages;

use App\Filament\Resources\SDGZielResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateSDGZiel extends CreateRecord
{
    use Translatable;

    protected static string $resource = SDGZielResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }
}
