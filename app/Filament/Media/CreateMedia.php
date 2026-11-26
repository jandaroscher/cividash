<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Media;

use Awcodes\Curator\Resources\Media\Pages\CreateMedia as CuratorCreateMedia;

/** Without a title, the file name becomes the title in the default content language. */
class CreateMedia extends CuratorCreateMedia
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $default = config('app.default_content_locale');
        if (blank($data['title'][$default] ?? null)) {
            $data['title'][$default] = pathinfo((string) $data['originalFilename'], PATHINFO_FILENAME);
        }

        unset($data['originalFilename']);

        return $data;
    }
}
