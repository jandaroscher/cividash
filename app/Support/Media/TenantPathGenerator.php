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
        return trim(($baseDir ? $baseDir.'/' : '').static::directory(Filament::getTenant()?->getKey() ?? 'default'), '/');
    }

    /** The folder of a dashboard's files; uploads and adopted files both go here. */
    public static function directory(int|string $tenantId): string
    {
        return 'media/'.$tenantId;
    }
}
