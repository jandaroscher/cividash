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

    protected array $categoryGroupState = [];

    /**
     * Get the header actions displayed on the page.
     *
     * @return array<int, Actions\Action> An array of action instances to render in the page header.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }

    /**
     * Extracts category group selections from the incoming form data, stores them on the page,
     * and returns the form data with that category group state removed.
     *
     * @param array $data The incoming form data, potentially including category group selections.
     * @return array The form data with category group state stripped out.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->categoryGroupState = TileResource::extractCategoryGroupState($data);

        return TileResource::stripCategoryGroupState($data);
    }

    /**
     * Synchronizes category group selections for the created Tile using the stored selection state.
     *
     * Applies the category group state captured during form processing to the newly created record.
     */
    protected function afterCreate(): void
    {
        TileResource::syncCategoryGroupSelections($this->record, $this->categoryGroupState);
    }
}