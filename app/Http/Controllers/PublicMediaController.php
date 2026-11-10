<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\CorruptedPathDetected;
use League\Flysystem\PathTraversalDetected;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a file from the public disk under /storage/<path>.
 *
 * With the local driver nginx serves these files straight from the volume and this
 * controller is only a fallback. With object storage it lets the bucket stay private:
 * the app reads with its credentials and the browser only talks to the app.
 */
class PublicMediaController extends Controller
{
    public function __invoke(string $path): StreamedResponse
    {
        // Dotfiles (e.g. .gitignore on the local volume) are never media.
        abort_if(preg_match('#(^|/)\.#', $path) === 1, 404);

        $disk = Storage::disk('public');

        try {
            abort_unless($disk->fileExists($path), 404);
        } catch (PathTraversalDetected|CorruptedPathDetected) {
            abort(404);
        }

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
            // Uploaded SVGs must not run script on the app origin when opened directly.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
