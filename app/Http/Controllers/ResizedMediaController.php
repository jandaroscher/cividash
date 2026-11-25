<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Http\Controllers;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\CorruptedPathDetected;
use League\Flysystem\PathTraversalDetected;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a content image from the public disk scaled down to one of a few fixed
 * widths, under /storage/_resized/<width>/<path>, for srcset in the frontend.
 *
 * The result is stored at that same path on the public disk. With the local driver
 * the web server then answers it as a static file (nginx try_files, Apache -f), so
 * PHP only runs on the first request per image and width; with object storage every
 * request streams the stored copy like PublicMediaController. The fixed widths bound
 * the work and storage an anonymous client can cause.
 */
class ResizedMediaController extends Controller
{
    public const WIDTHS = [480, 960, 1600];

    // Source plus scaled copy at 4 bytes per pixel must stay well below the PHP
    // memory limit (256M); larger images are stored and served unscaled.
    private const MAX_PIXELS = 40_000_000;

    private const HEADERS = [
        'Cache-Control' => 'public, max-age=604800',
        'X-Content-Type-Options' => 'nosniff',
        'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
    ];

    public function __invoke(int $width, string $path): StreamedResponse
    {
        $path = ltrim($path, '/');
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

        if (! $disk->fileExists($target)) {
            // shortcut: a replaced source at the same path keeps its old copies; uploads get unique names, so only reseeded files are affected.
            $source = $disk->get($path);
            $this->store($disk, $target, $this->resize($source, $width) ?? $source);
        }

        return $disk->response($target, null, self::HEADERS);
    }

    /** Writes via a temporary file so the web server never serves a half-written copy. */
    private function store(Filesystem $disk, string $target, string $bytes): void
    {
        $temporary = '_resized/.tmp/'.Str::random(32);
        $disk->put($temporary, $bytes);
        if (! $disk->exists($target)) {
            $disk->move($temporary, $target);
        } else {
            $disk->delete($temporary);
        }
    }

    /** Scaled image bytes in the source format, or null when the source is served as is. */
    private function resize(string $bytes, int $width): ?string
    {
        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            return null;
        }

        $quarterTurns = $info[2] === IMAGETYPE_JPEG ? $this->exifQuarterTurns($bytes) : 0;
        [$sourceWidth, $sourceHeight] = $quarterTurns % 2 === 1 ? [$info[1], $info[0]] : [$info[0], $info[1]];
        $height = (int) round($sourceHeight * $width / $sourceWidth);
        if ($sourceWidth <= $width || $info[0] * $info[1] + $width * $height > self::MAX_PIXELS) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }
        if ($quarterTurns > 0) {
            $rotated = imagerotate($image, -90 * $quarterTurns, 0);
            imagedestroy($image);
            $image = $rotated;
        }

        $scaled = imagescale($image, $width, $height);
        imagedestroy($image);
        if ($scaled === false) {
            return null;
        }
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);

        ob_start();
        match ($info[2]) {
            IMAGETYPE_PNG => imagepng($scaled, null, 9),
            IMAGETYPE_WEBP => imagewebp($scaled, null, 82),
            default => imagejpeg($scaled, null, 82),
        };
        imagedestroy($scaled);

        return ob_get_clean();
    }

    /** Clockwise quarter turns from the EXIF orientation; browsers apply it, GD does not. */
    private function exifQuarterTurns(string $bytes): int
    {
        if (! function_exists('exif_read_data')) {
            return 0;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));

        return match ($exif['Orientation'] ?? 1) {
            6 => 1,
            3 => 2,
            8 => 3,
            default => 0,
        };
    }
}
