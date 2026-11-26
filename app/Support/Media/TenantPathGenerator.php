<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Support\Media;

use Awcodes\Curator\PathGenerators\Contracts\PathGenerator;
use Filament\Facades\Filament;

/** Keeps each dashboard's uploads in its own folder: media/<tenant-id>. */
class TenantPathGenerator implements PathGenerator
{
    public function getPath(?string $baseDir = null): string
    {
        $tenant = Filament::getTenant()?->getKey() ?? 'default';

        return trim(($baseDir ? $baseDir.'/' : '').'media/'.$tenant, '/');
    }
}
