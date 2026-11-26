<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Support\Media;

use App\Http\Controllers\ResizedMediaController;
use Awcodes\Curator\Concerns\UrlProvider;
use Illuminate\Support\Facades\Storage;

/**
 * Library previews through the app's own resize route instead of Glide, so they need no
 * extra cache directory and work for a private bucket. Other types get the original file.
 */
class ResizedUrlProvider implements UrlProvider
{
    public static function getThumbnailUrl(string $path): string
    {
        return static::url($path, ResizedMediaController::WIDTHS[0]);
    }

    public static function getMediumUrl(string $path): string
    {
        return static::url($path, ResizedMediaController::WIDTHS[1]);
    }

    public static function getLargeUrl(string $path): string
    {
        return static::url($path, ResizedMediaController::WIDTHS[2]);
    }

    protected static function url(string $path, int $width): string
    {
        if (preg_match('/\.(jpe?g|png|webp)$/i', $path) === 1) {
            return route('media.resized', ['width' => $width, 'path' => ltrim($path, '/')], false);
        }

        return Storage::disk('public')->url($path);
    }
}
