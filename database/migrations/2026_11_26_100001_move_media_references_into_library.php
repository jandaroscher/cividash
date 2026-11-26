<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use App\Services\Media\MediaLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Adds every file that content references to the media library of its dashboard and
 * stores the library id instead of the path. Missing files keep their path. Where the
 * migration runs without the media volume, media:adopt-existing does it later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('media:adopt-existing', [], app()->runningUnitTests() ? null : new ConsoleOutput);
    }

    public function down(): void
    {
        app(MediaLibrary::class)->convertIdsToPaths();
    }
};
