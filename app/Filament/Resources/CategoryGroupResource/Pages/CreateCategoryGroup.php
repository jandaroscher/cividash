<?php

namespace App\Filament\Resources\CategoryGroupResource\Pages;

use App\Filament\Resources\CategoryGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCategoryGroup extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;

    protected static string $resource = CategoryGroupResource::class;

    /**
     * Provide header actions for the create page.
     *
     * @return array An array of Filament header action instances; currently contains a LocaleSwitcher action to change the active locale. 
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }
}