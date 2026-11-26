<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Media;

use Awcodes\Curator\Resources\Media\Tables\MediaTable as CuratorMediaTable;
use Filament\Tables\Table;

/** Curator's media table, searching the title of each language instead of its raw JSON. */
class MediaTable extends CuratorMediaTable
{
    public static function configure(Table $table): Table
    {
        return parent::configure($table)->searchable([...MediaPanel::translatedColumns('title'), 'caption', 'description']);
    }
}
