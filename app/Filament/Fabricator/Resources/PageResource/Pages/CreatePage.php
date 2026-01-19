<?php

namespace App\Filament\Fabricator\Resources\PageResource\Pages;

use App\Filament\Fabricator\Resources\PageResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Z3d0X\FilamentFabricator\Resources\PageResource\Pages\CreatePage as FabricatorCreatePage;

class CreatePage extends FabricatorCreatePage
{
    use Translatable;

    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        $actions = array_values(array_filter(
            parent::getHeaderActions(),
            fn ($action) => ! method_exists($action, 'getName') || $action->getName() !== 'preview'
        ));

        return [
            Actions\LocaleSwitcher::make(),
            ...$actions,
        ];
    }
}

