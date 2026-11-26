<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Support;

use App\Services\Media\MediaLibrary;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Facades\Filament;

/**
 * Picks a file from the dashboard's media library and stores its id.
 *
 * A value that is still a file path (written through the admin API, a bundle import
 * or a theme default) is added to the library when the form loads, so saving the form
 * keeps the file instead of clearing the field.
 */
class MediaPicker extends CuratorPicker
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantAware()
            ->disk('public')
            ->buttonLabel(__('filament.media.picker_button'))
            ->constrained();
    }

    public function callAfterStateHydrated(): static
    {
        $state = $this->getRawState();
        $tenantId = Filament::getTenant()?->getKey();

        if (is_string($state) && $state !== '' && ! MediaLibrary::isId($state) && $tenantId !== null) {
            $media = app(MediaLibrary::class)->adopt($state, (int) $tenantId);
            if ($media) {
                $this->state($media->getKey());
            }
        }

        return parent::callAfterStateHydrated();
    }
}
