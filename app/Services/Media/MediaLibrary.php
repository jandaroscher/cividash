<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Content fields store the id of a media library item. Older content, theme defaults
 * and values written through the admin API hold a path on the 'public' disk instead.
 * This service turns either form into the stored path, so API payloads keep their
 * shape, and knows every place content keeps a media reference.
 */
class MediaLibrary
{
    /** Keys that hold a media reference inside block and repeater data. */
    public const DATA_KEYS = ['image', 'image_secondary', 'file', 'icon'];

    /** Image keys whose `<key>_title`, `<key>_alt` and `<key>_copyright` fall back to the library. */
    public const METADATA_KEYS = ['image', 'image_secondary'];

    /** Scalar columns holding a media reference, per table. */
    private const COLUMNS = [
        'pages' => ['meta_image'],
        'tiles' => ['icon', 'meta_image'],
        'metric_definitions' => ['icon'],
    ];

    /** JSON columns holding block or repeater data, per table. */
    private const JSON_COLUMNS = [
        'pages' => ['blocks'],
        'tiles' => ['background_blocks'],
        'footer_navigations' => ['social_links', 'sponsors'],
    ];

    /** Settings properties holding a media reference, as [group, name]. */
    private const SETTINGS = [['branding', 'logo_url'], ['general', 'favicon']];

    /** @var array<int, Media|null> */
    private array $loaded = [];

    /** A stored value is a library id when it is an integer or a string of digits. */
    public static function isId(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && ctype_digit($value));
    }

    public function find(mixed $reference): ?Media
    {
        if (! static::isId($reference)) {
            return null;
        }

        $id = (int) $reference;

        return $this->loaded[$id] ??= Media::query()->find($id);
    }

    /** The stored path for a library id; any other non-empty string is taken as a path already. */
    public function path(mixed $reference): ?string
    {
        if (static::isId($reference)) {
            return $this->find($reference)?->path;
        }

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    public function url(mixed $reference): ?string
    {
        $path = $this->path($reference);

        return $path === null ? null : Storage::disk('public')->url($path);
    }

    /**
     * Resolves media references in block or repeater data to paths. Empty title, alt
     * text and copyright of an image fall back to the values kept in the library.
     */
    public function resolveData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->resolveData($value);

                continue;
            }

            if (! in_array($key, self::DATA_KEYS, true) || ! static::isId($value)) {
                continue;
            }

            $media = $this->find($value);
            $data[$key] = $media?->path;

            if ($media && in_array($key, self::METADATA_KEYS, true)) {
                foreach (['title', 'alt', 'copyright'] as $field) {
                    if (blank($data["{$key}_{$field}"] ?? null) && filled($media->{$field})) {
                        $data["{$key}_{$field}"] = $media->{$field};
                    }
                }
            }
        }

        return $data;
    }

    /**
     * The library item of a tenant for a file on the 'public' disk, created when the
     * file exists but is not in the library yet. A file another dashboard already owns
     * is copied, so deleting one dashboard's item never removes another's file.
     */
    public function adopt(string $path, int $tenantId): ?Media
    {
        $path = ltrim($path, '/');
        $path = Str::startsWith($path, 'storage/') ? Str::after($path, 'storage/') : $path;
        $disk = Storage::disk('public');

        $existing = Media::query()->where('tenant_id', $tenantId)->where('path', $path)->first();
        if ($existing) {
            return $existing;
        }

        try {
            if ($path === '' || ! $disk->fileExists($path)) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        if (Media::query()->where('path', $path)->exists()) {
            $copy = 'media/'.$tenantId.'/'.Str::random(8).'-'.basename($path);
            $disk->copy($path, $copy);
            $path = $copy;
        }

        $info = pathinfo($path);
        $type = $disk->mimeType($path) ?: 'application/octet-stream';
        $size = @getimagesizefromstring(str_starts_with($type, 'image/') ? (string) $disk->get($path) : '');

        $media = new Media;
        $media->forceFill([
            'tenant_id' => $tenantId,
            'disk' => 'public',
            'directory' => ($info['dirname'] ?? '.') === '.' ? null : $info['dirname'],
            'visibility' => 'public',
            'name' => $info['filename'],
            'path' => $path,
            'ext' => strtolower($info['extension'] ?? ''),
            'type' => $type,
            'size' => $disk->size($path),
            'width' => $size ? $size[0] : null,
            'height' => $size ? $size[1] : null,
        ])->saveQuietly();

        return $media;
    }

    /**
     * Replaces stored paths with library ids in every place content keeps a media
     * reference. Safe to run again: ids stay as they are, and a path whose file is
     * missing is left unchanged and logged.
     */
    public function convertPathsToIds(): void
    {
        $toId = function (mixed $value, int $tenantId): mixed {
            if (! is_string($value) || $value === '' || static::isId($value)) {
                return $value;
            }

            $media = $this->adopt($value, $tenantId);
            if (! $media) {
                Log::warning('Media library: file not found, reference kept as path.', ['path' => $value, 'tenant_id' => $tenantId]);

                return $value;
            }

            return $media->getKey();
        };

        $this->rewriteReferences($toId);
    }

    /** Stores paths again in place of library ids, so the previous release can read the content. */
    public function convertIdsToPaths(): void
    {
        $this->rewriteReferences(fn (mixed $value): mixed => static::isId($value) ? ($this->path($value) ?? $value) : $value);
    }

    /** @param \Closure(mixed, int): mixed $map */
    private function rewriteReferences(\Closure $map): void
    {
        foreach ($this->rows() as [$table, $column, $kind, $row]) {
            $value = $this->decodeColumn($row->{$column}, $kind);
            $converted = $kind !== 'json'
                ? $map($value, (int) $row->tenant_id)
                : $this->mapData($value, fn ($v) => $map($v, (int) $row->tenant_id));

            if ($converted !== $value) {
                DB::table($table)->where('id', $row->id)->update([$column => $this->encodeColumn($converted, $kind)]);
            }
        }
    }

    /**
     * Where a library item is used, as a list of [type, id, label] entries.
     *
     * @return list<array{type: string, id: int, label: string}>
     */
    public function usages(Media $media): array
    {
        $usages = [];
        $matches = fn (mixed $value): bool => static::isId($value) && (int) $value === (int) $media->getKey();

        foreach ($this->rows((int) $media->tenant_id) as [$table, $column, $kind, $row]) {
            $value = $this->decodeColumn($row->{$column}, $kind);
            $found = false;

            if ($kind !== 'json') {
                $found = $matches($value);
            } else {
                $this->mapData($value, function ($v) use ($matches, &$found) {
                    $found = $found || $matches($v);

                    return $v;
                });
            }

            if ($found) {
                $usages[$table.':'.$row->id] = ['type' => $table, 'id' => (int) $row->id, 'label' => $this->label($table, $row)];
            }
        }

        return array_values($usages);
    }

    /** Applies $map to every value under a media key, at any depth. */
    private function mapData(mixed $data, \Closure $map): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->mapData($value, $map);
            } elseif (in_array($key, self::DATA_KEYS, true)) {
                $data[$key] = $map($value);
            }
        }

        return $data;
    }

    /**
     * Every stored media reference: [table, column, kind, row]. Kind is 'scalar' (plain
     * column), 'scalar-json' (JSON-encoded settings payload) or 'json' (block data).
     *
     * @return \Generator<array{string, string, string, object}>
     */
    private function rows(?int $tenantId = null): \Generator
    {
        $tables = array_map(fn ($columns) => array_fill_keys($columns, 'scalar'), self::COLUMNS);
        foreach (self::JSON_COLUMNS as $table => $columns) {
            $tables[$table] = array_merge($tables[$table] ?? [], array_fill_keys($columns, 'json'));
        }

        foreach ($tables as $table => $columns) {
            $query = DB::table($table)->whereNotNull('tenant_id')->orderBy('id');
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }

            foreach ($query->get() as $row) {
                foreach ($columns as $column => $kind) {
                    if ($row->{$column} !== null) {
                        yield [$table, $column, $kind, $row];
                    }
                }
            }
        }

        // Settings of a dashboard only; global defaults and theme values stay paths.
        foreach (self::SETTINGS as [$group, $name]) {
            $query = DB::table('settings')->where('group', $group)->where('name', $name)->where('tenant_id', '>', 0)->orderBy('id');
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }

            foreach ($query->get() as $row) {
                yield ['settings', 'payload', 'scalar-json', $row];
            }
        }
    }

    private function decodeColumn(mixed $raw, string $kind): mixed
    {
        return $kind === 'scalar' ? $raw : json_decode((string) $raw, true);
    }

    private function encodeColumn(mixed $value, string $kind): mixed
    {
        return $kind === 'scalar' ? $value : json_encode($value);
    }

    private function label(string $table, object $row): string
    {
        $title = match ($table) {
            'pages', 'tiles' => $this->translated($row->title ?? null),
            'metric_definitions' => $this->translated($row->label ?? null),
            'settings' => $row->group.'.'.$row->name,
            default => null,
        };

        return __('filament.media.usage_types.'.$table).($title ? ': '.$title : '');
    }

    private function translated(mixed $raw): ?string
    {
        $value = is_string($raw) ? (json_decode($raw, true) ?? $raw) : $raw;

        return is_array($value) ? ($value[app()->getLocale()] ?? $value['de'] ?? reset($value) ?: null) : $value;
    }
}
