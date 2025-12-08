<?php

namespace App\Filament\Resources\FooterNavigationResource\Pages;

use App\Filament\Resources\FooterNavigationResource;
use Filament\Resources\Pages\ListRecords;

class ListFooterNavigations extends ListRecords
{
    protected static string $resource = FooterNavigationResource::class;

    /**
     * Redirects the current page to the resource's edit URL for the singleton record with ID 1.
     *
     * Treats the resource as a singleton by sending users from the list page directly to the single record's edit view.
     */
    public function mount(): void
    {
        $this->redirect(static::getResource()::getUrl('edit', ['record' => 1]));
    }
}