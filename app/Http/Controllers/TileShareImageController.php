<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Http\Controllers;

use App\Models\Tile;
use App\Services\Sharing\TileShareImage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class TileShareImageController extends Controller
{
    public function __invoke(Request $request, int $tile, TileShareImage $image): Response
    {
        $tile = Tile::where('is_public', true)->findOrFail($tile);
        $locale = $request->query('locale') === 'en' ? 'en' : 'de';

        $content = $image->content($tile, $locale);
        $version = $image->version($content);

        // The cache store, not the filesystem, so it works on a read-only root
        // filesystem. Base64 because the database store cannot hold raw bytes on Postgres.
        $png = base64_decode(Cache::remember(
            "tile-share-image:{$tile->tenant_id}:{$tile->id}:{$version}",
            now()->addDays(30),
            fn () => base64_encode($image->render($content)),
        ));

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
            'ETag' => "\"{$version}\"",
        ]);
    }
}
