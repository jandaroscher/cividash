<?php

namespace App\Filament\Resources\HandlungsfeldResource\Pages;

use App\Filament\Resources\HandlungsfeldResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateHandlungsfeld extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;

    protected static string $resource = HandlungsfeldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }
}

