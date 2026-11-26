<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Models;

use App\Exceptions\MediaInUseException;
use App\Models\Concerns\BelongsToTenant;
use App\Services\Media\MediaLibrary;
use Awcodes\Curator\Models\Media as CuratorMedia;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file in the media library. Content fields store its id; the API resolves the id
 * to the stored path (see App\Services\Media\MediaLibrary). Queries only see the
 * current dashboard's files.
 */
class Media extends CuratorMedia
{
    use BelongsToTenant;

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

    protected static function booted(): void
    {
        // Every upload path (create page, multi upload, picker) creates the model, so this covers them all
        static::creating(function (Media $media) {
            $media->uploaded_by ??= auth()->id();
        });

        // A file that content still uses is never deleted, so no page loses its image
        static::deleting(function (Media $media) {
            if (app(MediaLibrary::class)->usages($media) !== []) {
                throw new MediaInUseException;
            }
        });
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
