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
use Spatie\Translatable\HasTranslations;

/**
 * A file in the media library. Content fields store its id; the API resolves the id
 * to the stored path (see App\Services\Media\MediaLibrary). Queries only see the
 * current dashboard's files.
 *
 * Alt text and title are kept per language. Read or written as a plain string (as the
 * media library package does), they are the default content language's value.
 */
class Media extends CuratorMedia
{
    use BelongsToTenant;
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['alt', 'title'];

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

        // Page responses are cached and show the library's texts where blocks leave them empty
        static::saved(function (Media $media) {
            if ($media->wasChanged(['alt', 'title', 'copyright'])) {
                $pages = collect(app(MediaLibrary::class)->usages($media))->where('type', 'pages')->pluck('id');
                Page::withoutGlobalScopes()->whereKey($pages)->get()->each->flushContentCache();
            }
        });

        // A file that content still uses is never deleted, so no page loses its image
        static::deleting(function (Media $media) {
            if (app(MediaLibrary::class)->usages($media) !== []) {
                throw new MediaInUseException;
            }
        });
    }

    public function getLocale(): string
    {
        return $this->translationLocale ?: config('app.default_content_locale');
    }

    /** The package and its picker read alt and title from the array form, so they stay strings there. */
    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();
        foreach (array_intersect($this->getTranslatableAttributes(), array_keys($attributes)) as $key) {
            $attributes[$key] = $this->getAttributeValue($key);
        }

        return $attributes;
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
