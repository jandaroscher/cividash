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
 * Serves a content image from the public disk scaled down to one of a few fixed
 * widths, under /storage/_resized/<width>/<path>, for srcset in the frontend.
 *
 * The copy is written to the same path on the public disk, so from then on the web
 * server answers it as a static file (nginx try_files, Apache -f) and PHP only runs
 * on the first request per image and width. The fixed widths bound the work and
 * storage an anonymous client can cause.
 */
class ResizedMediaController extends Controller
{
    public const WIDTHS = [480, 960, 1600];

    // Beyond this GD would need more memory than the PHP limit allows; such images
    // are served unscaled.
    private const MAX_SOURCE_PIXELS = 40_000_000;

    private const CACHE_HEADERS = [
        'Cache-Control' => 'public, max-age=604800',
        'X-Content-Type-Options' => 'nosniff',
    ];

    public function __invoke(int $width, string $path): StreamedResponse
    {
        abort_unless(in_array($width, self::WIDTHS, true), 404);
        abort_if(preg_match('#(^|/)\.#', $path) === 1 || str_starts_with($path, '_resized/'), 404);
        abort_unless(preg_match('/\.(jpe?g|png|webp)$/i', $path) === 1, 404);

        $disk = Storage::disk('public');
        $target = "_resized/{$width}/{$path}";

        try {
            abort_unless($disk->fileExists($path), 404);
        } catch (PathTraversalDetected|CorruptedPathDetected) {
            abort(404);
        }

        if ($disk->fileExists($target)) {
            return $disk->response($target, null, self::CACHE_HEADERS);
        }

        // shortcut: a replaced source at the same path keeps its old copies; uploads get unique names, so only reseeded files are affected.
        $resized = $this->resize($disk->get($path), $width);
        if ($resized === null) {
            return $disk->response($path, null, self::CACHE_HEADERS);
        }

        $disk->put($target, $resized);

        return $disk->response($target, null, self::CACHE_HEADERS);
    }

    /** Scaled image bytes in the source format, or null when the source should be served as is. */
    private function resize(string $bytes, int $width): ?string
    {
        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info[0] <= $width || $info[0] * $info[1] > self::MAX_SOURCE_PIXELS) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }

        $scaled = imagescale($source, $width);
        imagedestroy($source);
        if ($scaled === false) {
            return null;
        }

        ob_start();
        match ($info[2]) {
            IMAGETYPE_PNG => imagepng($scaled, null, 9),
            IMAGETYPE_WEBP => imagewebp($scaled, null, 82),
            default => imagejpeg($scaled, null, 82),
        };
        imagedestroy($scaled);

        return ob_get_clean();
    }
}
