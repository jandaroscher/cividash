<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Console\Commands;

use App\Services\Media\MediaLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Adds every file that content references by path to the media library of its
 * dashboard and stores the library id instead. Safe to run again. It needs the
 * media volume: run it where the uploads are mounted.
 */
class MediaAdoptExistingCommand extends Command
{
    protected $signature = 'media:adopt-existing';

    protected $description = 'Move files that content references by path into the media library.';

    public function handle(MediaLibrary $library): int
    {
        $stats = $library->convertPathsToIds();

        $this->components->info(sprintf(
            'Media library: %d path references, %d added to the library, %d files not found (kept as paths).',
            $stats['references'], $stats['adopted'], $stats['missing'],
        ));

        if ($stats['missing'] > 0 && $stats['adopted'] === 0) {
            $message = 'Media library: none of the referenced files were found. If the uploads are not mounted here, '
                .'run `php artisan media:adopt-existing` where they are (e.g. in the app container).';
            Log::warning($message, $stats);
            $this->components->warn($message);
        }

        return self::SUCCESS;
    }
}
