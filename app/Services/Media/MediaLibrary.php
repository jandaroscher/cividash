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
 *
 * Ids resolve within the current dashboard only (the Media model is tenant scoped),
 * so content can never point at another dashboard's file.
 */
class MediaLibrary
{
    /** Keys that hold a media reference inside block and repeater data. */
    public const DATA_KEYS = ['image', 'image_secondary', 'file', 'icon'];

    /** Image keys whose `<key>_title`, `<key>_alt` and `<key>_copyright` fall back to the library. */
    public const METADATA_KEYS = ['image', 'image_secondary'];

    /** Every type a content field accepts, so the library takes no file no field could use. */
    public const UPLOAD_TYPES = [
        'image/*',
        'image/x-icon',
        'image/vnd.microsoft.icon',
        'application/json',
        'application/zip+dotlottie',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
    ];

    /**
     * Columns holding a media reference, per table, with their kind: 'scalar' (plain
     * column), 'localized' (a reference or a JSON object of one per locale) or 'json'
     * (block or repeater data).
     */
    private const COLUMNS = [
        'pages' => ['meta_image' => 'scalar', 'blocks' => 'json'],
        'tiles' => ['icon' => 'scalar', 'meta_image' => 'scalar', 'background_blocks' => 'json'],
        'metric_definitions' => ['icon' => 'scalar'],
        'categories' => ['icon' => 'localized'],
        'footer_navigations' => ['social_links' => 'json', 'sponsors' => 'json'],
    ];

    /** The column naming a row in the list of usages, per table. */
    private const LABEL_COLUMNS = ['pages' => 'title', 'tiles' => 'title', 'metric_definitions' => 'label', 'categories' => 'slug'];

    /** Settings properties holding a media reference, as [group, name]. */
    private const SETTINGS = [['branding', 'logo_url'], ['general', 'favicon']];

    /** @var array<int, Media|null> */
    private array $loaded = [];

    /** A stored value is a library id when it is an integer or a string of digits. */
    public static function isId(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && ctype_digit($value));
    }

    /** A stored path as the 'public' disk knows it: without a leading slash or `storage/`. */
    public static function normalizePath(string $path): string
    {
        $path = ltrim($path, '/');

        return Str::startsWith($path, 'storage/') ? Str::after($path, 'storage/') : $path;
    }

    /**
     * The library ids referenced by a field value: a single id, or block and repeater data.
     *
     * @return list<int>
     */
    public static function idsIn(mixed $value): array
    {
        if (static::isId($value)) {
            return [(int) $value];
        }

        $ids = [];
        if (is_array($value)) {
            array_walk_recursive($value, function ($item, $key) use (&$ids) {
                if (in_array($key, self::DATA_KEYS, true) && static::isId($item)) {
                    $ids[] = (int) $item;
                }
            });
        }

        return array_values(array_unique($ids));
    }

    public function find(mixed $reference): ?Media
    {
        if (! static::isId($reference)) {
            return null;
        }

        $id = (int) $reference;

        return array_key_exists($id, $this->loaded)
            ? $this->loaded[$id]
            : $this->loaded[$id] = Media::query()->find($id);
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

    /** A localized reference (a JSON object of one per locale, or a plain one) with paths in place of ids. */
    public function localizedPath(?string $raw): ?string
    {
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded)
            ? json_encode(array_map(fn ($v) => $this->path($v), $decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : $this->path($raw);
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
     * The library item of a dashboard for a file on the 'public' disk. A file that is
     * not in the library yet is copied into the dashboard's folder, and the original
     * stays where it is: theme defaults and other dashboards may still use it, and
     * deleting or replacing the library item must never touch it.
     */
    public function adopt(string $path, int $tenantId): ?Media
    {
        $path = static::normalizePath($path);
        if ($path === '') {
            return null;
        }

        $existing = Media::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(fn ($query) => $query->where('source_path', $path)->orWhere('path', $path))
            ->first();
        if ($existing) {
            return $existing;
        }

        $disk = Storage::disk('public');
        try {
            if (! $disk->fileExists($path)) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        // Keep the copy's path within the 255 characters of the column.
        $directory = 'media/'.$tenantId;
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $suffix = $extension === '' ? '' : '.'.$extension;
        $name = mb_strcut(pathinfo($path, PATHINFO_FILENAME), 0, 255 - strlen($directory.'/12345678-'.$suffix));
        $copy = $directory.'/'.Str::lower(Str::random(8)).'-'.$name.$suffix;
        $disk->copy($path, $copy);

        $info = pathinfo($copy);
        $type = $disk->mimeType($copy) ?: 'application/octet-stream';
        $size = @getimagesizefromstring(str_starts_with($type, 'image/') ? (string) $disk->get($copy) : '');

        $media = new Media;
        $media->forceFill([
            'tenant_id' => $tenantId,
            'disk' => 'public',
            'directory' => $directory,
            'visibility' => 'public',
            'name' => $info['filename'],
            'title' => pathinfo($path, PATHINFO_FILENAME),
            'path' => $copy,
            'source_path' => $path,
            'ext' => strtolower($info['extension'] ?? ''),
            'type' => $type,
            'size' => $disk->size($copy),
            'width' => $size ? $size[0] : null,
            'height' => $size ? $size[1] : null,
        ])->saveQuietly();

        return $media;
    }

    /**
     * Replaces stored paths with library ids in every place content keeps a media
     * reference. Safe to run again: ids stay as they are, and a path whose file is
     * missing is left unchanged and logged.
     *
     * @return array{references: int, adopted: int, missing: int}
     */
    public function convertPathsToIds(): array
    {
        $stats = ['references' => 0, 'adopted' => 0, 'missing' => 0];

        $this->rewriteReferences(function (mixed $value, int $tenantId) use (&$stats): mixed {
            if (! is_string($value) || $value === '' || static::isId($value)) {
                return $value;
            }

            $stats['references']++;
            $media = $this->adopt($value, $tenantId);
            if (! $media) {
                $stats['missing']++;
                Log::warning('Media library: file not found, reference kept as path.', ['path' => $value, 'tenant_id' => $tenantId]);

                return $value;
            }

            $stats['adopted']++;

            return $media->getKey();
        });

        return $stats;
    }

    /** Stores paths again in place of library ids, so the previous release can read the content. */
    public function convertIdsToPaths(): void
    {
        $this->rewriteReferences(function (mixed $value): mixed {
            if (! static::isId($value)) {
                return $value;
            }

            return Media::withoutGlobalScopes()->find((int) $value)?->path ?? $value;
        });
    }

    /** @param \Closure(mixed, int): mixed $map */
    private function rewriteReferences(\Closure $map): void
    {
        foreach ($this->rows() as [$table, $column, $kind, $row]) {
            $value = $this->decodeColumn($row->{$column}, $kind);
            $converted = $this->mapColumn($value, $kind, fn ($v) => $map($v, (int) $row->tenant_id));

            if ($converted !== $value) {
                DB::table($table)->where('id', $row->id)->update([$column => $this->encodeColumn($converted, $kind)]);
            }
        }
    }

    /**
     * Where a library item is used, as a list of [type, id, label] entries. Content that
     * still holds the file's path, or the path it was copied from, counts as a use too.
     *
     * @return list<array{type: string, id: int, label: string}>
     */
    public function usages(Media $media): array
    {
        $paths = array_filter([$media->path, $media->source_path]);
        $matches = fn (mixed $value): bool => static::isId($value)
            ? (int) $value === (int) $media->getKey()
            : is_string($value) && $value !== '' && in_array(static::normalizePath($value), $paths, true);

        $usages = [];
        foreach ($this->rows((int) $media->tenant_id) as [$table, $column, $kind, $row]) {
            $found = false;
            $this->mapColumn($this->decodeColumn($row->{$column}, $kind), $kind, function ($v) use ($matches, &$found) {
                $found = $found || $matches($v);

                return $v;
            });

            if ($found) {
                $usages[$table.':'.$row->id] = ['type' => $table, 'id' => (int) $row->id, 'label' => $this->label($table, $row)];
            }
        }

        return array_values($usages);
    }

    /** Applies $map to every media reference in a column value of the given kind. */
    private function mapColumn(mixed $value, string $kind, \Closure $map): mixed
    {
        return match ($kind) {
            'json' => $this->mapData($value, $map),
            'localized' => is_array($value) ? array_map($map, $value) : $map($value),
            default => $map($value),
        };
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
     * Every stored media reference: [table, column, kind, row]. Kind 'scalar-json' is a
     * JSON-encoded settings payload.
     *
     * @return \Generator<array{string, string, string, object}>
     */
    private function rows(?int $tenantId = null): \Generator
    {
        foreach (self::COLUMNS as $table => $columns) {
            $select = array_merge(['id', 'tenant_id'], array_keys($columns), array_filter([self::LABEL_COLUMNS[$table] ?? null]));
            $query = DB::table($table)->select(array_unique($select))->whereNotNull('tenant_id');
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }

            foreach ($query->lazyById() as $row) {
                foreach ($columns as $column => $kind) {
                    if ($row->{$column} !== null) {
                        yield [$table, $column, $kind, $row];
                    }
                }
            }
        }

        // Settings of a dashboard only; global defaults and theme values stay paths.
        foreach (self::SETTINGS as [$group, $name]) {
            $query = DB::table('settings')->select(['id', 'tenant_id', 'group', 'name', 'payload'])
                ->where('group', $group)->where('name', $name)->where('tenant_id', '>', 0);
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }

            foreach ($query->lazyById() as $row) {
                yield ['settings', 'payload', 'scalar-json', $row];
            }
        }
    }

    private function decodeColumn(mixed $raw, string $kind): mixed
    {
        if ($kind === 'scalar') {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        // A localized column may hold a plain path instead of a JSON object.
        return $kind === 'localized' && ! is_array($decoded) ? $raw : $decoded;
    }

    private function encodeColumn(mixed $value, string $kind): mixed
    {
        return match (true) {
            $kind === 'scalar', $kind === 'localized' && ! is_array($value) => $value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }

    private function label(string $table, object $row): string
    {
        $title = match ($table) {
            'settings' => __("filament.media.usage_settings.{$row->group}.{$row->name}"),
            default => $this->translated($row->{self::LABEL_COLUMNS[$table] ?? ''} ?? null),
        };

        return __('filament.media.usage_types.'.$table).($title ? ': '.$title : '');
    }

    private function translated(mixed $raw): ?string
    {
        $value = is_string($raw) ? (json_decode($raw, true) ?? $raw) : $raw;

        return is_array($value) ? ($value[app()->getLocale()] ?? $value['de'] ?? reset($value) ?: null) : $value;
    }
}
