<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use App\Services\Media\MediaLibrary;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds every file that content references to the media library of its dashboard and
 * stores the library id instead of the path. Missing files keep their path.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(MediaLibrary::class)->convertPathsToIds();
    }

    public function down(): void
    {
        app(MediaLibrary::class)->convertIdsToPaths();
    }
};
