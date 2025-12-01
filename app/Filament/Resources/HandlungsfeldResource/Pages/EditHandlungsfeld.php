<?php

namespace App\Filament\Resources\HandlungsfeldResource\Pages;

use App\Filament\Resources\HandlungsfeldResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHandlungsfeld extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = HandlungsfeldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

