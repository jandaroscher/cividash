<?php

namespace App\Filament\Resources\NavigationResource\Pages;

use App\Filament\Resources\NavigationResource;
use Filament\Resources\Pages\ListRecords;

class ListNavigations extends ListRecords
{
    protected static string $resource = NavigationResource::class;

    /**
     * Redirect to edit page since this is a singleton resource.
     */
    public function mount(): void
    {
        $this->redirect(static::getResource()::getUrl('edit', ['record' => 1]));
    }
}
