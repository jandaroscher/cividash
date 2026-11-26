<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Media;

use Awcodes\Curator\Resources\Media\Pages\EditMedia as CuratorEditMedia;

/** Header actions in the order of the other edit pages: delete, view, save. */
class EditMedia extends CuratorEditMedia
{
    public function getHeaderActions(): array
    {
        [$save, $view, $delete] = parent::getHeaderActions();

        return [$delete, $view, $save];
    }
}
