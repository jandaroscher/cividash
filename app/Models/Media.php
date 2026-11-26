<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Models;

use App\Exceptions\MediaInUseException;
use App\Services\Media\MediaLibrary;
use Awcodes\Curator\Models\Media as CuratorMedia;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file in the media library. Content fields store its id; the API resolves the id
 * to the stored path (see App\Services\Media\MediaLibrary).
 */
class Media extends CuratorMedia
{
    protected $fillable = [
        'disk',
        'directory',
        'visibility',
        'name',
        'path',
        'width',
        'height',
        'size',
        'type',
        'ext',
        'alt',
        'title',
        'description',
        'caption',
        'copyright',
        'exif',
        'curations',
        'file',
    ];

    /** A file that content still uses is never deleted, so no page loses its image. */
    protected static function booted(): void
    {
        static::deleting(function (Media $media) {
            if (app(MediaLibrary::class)->usages($media) !== []) {
                throw new MediaInUseException;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
