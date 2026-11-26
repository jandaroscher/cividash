<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Media;

use Awcodes\Curator\Resources\Media\Pages\EditMedia as CuratorEditMedia;

/** Alt text and title per language, and header actions in the order of the other edit pages. */
class EditMedia extends CuratorEditMedia
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach ($this->record->getTranslatableAttributes() as $field) {
            $data[$field] = $this->record->getTranslations($field);
        }

        return $data;
    }

    public function getHeaderActions(): array
    {
        [$save, $view, $delete] = parent::getHeaderActions();

        return [$delete, $view, $save];
    }
}
