<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Resources\TileResource\Pages;

use App\Filament\Resources\TileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListTiles extends ListRecords
{
    use Translatable;

    protected static string $resource = TileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
