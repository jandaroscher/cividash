<?php

namespace App\Filament\Resources\CategoryGroupResource\Pages;

use App\Filament\Resources\CategoryGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategoryGroup extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = CategoryGroupResource::class;

    /**
     * Provide the actions to render in the page header.
     *
     * @return array The header action components to display on the edit page.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\DeleteAction::make(),
        ];
    }
}