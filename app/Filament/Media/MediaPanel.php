<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Media;

use App\Models\Media;
use Awcodes\Curator\Components\Modals\CuratorPanel;
use Filament\Actions\Action;

/** Curator's picker panel, whose "edit item" modal uses our per-language media fields. */
class MediaPanel extends CuratorPanel
{
    public function editItemAction(): Action
    {
        return parent::editItemAction()
            ->schema(MediaForm::getAdditionalInformationFormSchema())
            ->fillForm(function (array $arguments): array {
                $record = $this->resolveAuthorizedMedia($arguments, 'update');

                return $record instanceof Media
                    ? [...$record->toArray(), ...collect($record->getTranslatableAttributes())->mapWithKeys(fn (string $field) => [$field => $record->getTranslations($field)])]
                    : [];
            });
    }
}
