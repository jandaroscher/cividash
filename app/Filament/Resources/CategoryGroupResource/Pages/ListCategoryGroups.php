<?php

namespace App\Filament\Resources\CategoryGroupResource\Pages;

use App\Filament\Resources\CategoryGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategoryGroups extends ListRecords
{
    protected static string $resource = CategoryGroupResource::class;

    /**
     * Provide the header action buttons for the list page.
     *
     * @return array<int, \Filament\Pages\Actions\Action> An array of action instances to display in the page header.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}