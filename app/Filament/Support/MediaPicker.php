<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Support;

use App\Models\Media;
use App\Services\Media\MediaLibrary;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Awcodes\Curator\Resources\Media\MediaResource;
use Awcodes\Curator\Support\MediaScope;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Picks a file from the dashboard's media library and stores its id.
 *
 * A value that is still a file path (written through the admin API, a bundle import
 * or a theme default) is added to the library when the form loads. A path whose file
 * is missing, a theme file or the inherited theme or global value is shown as it is
 * and saved unchanged, so saving never clears it.
 */
class MediaPicker extends CuratorPicker
{
    protected bool $isLocalized = false;

    protected ?Closure $inheritedValue = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantAware()
            ->disk('public')
            ->buttonLabel(__('filament.media.picker_button'))
            ->constrained();

        $dehydrateIds = $this->dehydrateStateUsing;
        $this->dehydrateStateUsing(function (MediaPicker $component, mixed $state) use ($dehydrateIds): mixed {
            $value = $component->evaluate($dehydrateIds, ['state' => $state])
                ?? collect($state)->pluck('legacy_path')->filter()->first();

            return $component->isLocalized ? $component->mergeLocale($value) : $value;
        });

        // The library holds files of any size; each field takes only files up to its own limit.
        $this->rule(static fn (MediaPicker $component): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            $limit = $component->getMaxSize() * 1024;
            // Files saved before keep saving, so an older, larger file never blocks the form.
            $ids = array_diff(MediaScope::extractIds($value), $component->getPersistedMediaIds());
            $tooLarge = Media::query()->whereKey($ids)
                ->where('size', '>', $limit)->exists();

            if ($tooLarge) {
                $fail(__('filament.media.too_large', ['max' => Number::fileSize($limit)]));
            }
        });
    }

    public function getEditAction(): Action
    {
        // A stored path without a library item has nothing to edit.
        return parent::getEditAction()
            ->hidden(fn (MediaPicker $component, array $arguments): bool => $component->isDisabled() || blank($arguments['id'] ?? null))
            ->url(fn (array $arguments): ?string => filled($arguments['id'] ?? null) ? MediaResource::getUrl('edit', ['record' => $arguments['id']]) : null, true);
    }

    /** The field stores one reference per locale in a JSON object, like category icons. */
    public function localized(bool $condition = true): static
    {
        $this->isLocalized = $condition;

        return $this;
    }

    /**
     * The value the field inherits from the theme or the global defaults. That value
     * stays a path, so later theme or default changes still reach the dashboard.
     */
    public function inheritedValue(?Closure $value): static
    {
        $this->inheritedValue = $value;

        return $this;
    }

    public function callAfterStateHydrated(): static
    {
        $state = $this->getRawState();
        if ($this->isLocalized) {
            $state = $this->localeValue($state);
            $this->state($state);
        }

        $legacy = null;
        $tenantId = Filament::getTenant()?->getKey();
        if (is_string($state) && $state !== '' && ! MediaLibrary::isId($state) && $tenantId !== null) {
            $inherited = str_starts_with(MediaLibrary::normalizePath($state), 'themes/')
                || $state === $this->evaluate($this->inheritedValue);
            $media = $inherited ? null : app(MediaLibrary::class)->adopt($state, (int) $tenantId);
            $media ? $this->state($media->getKey()) : $legacy = $state;
        }

        parent::callAfterStateHydrated();

        if ($legacy !== null) {
            $file = new Media;
            $file->forceFill([
                ...array_fill_keys($file->getFillable(), null),
                'disk' => 'public', 'path' => $legacy, 'name' => pathinfo($legacy, PATHINFO_FILENAME),
                'ext' => strtolower(pathinfo($legacy, PATHINFO_EXTENSION)), 'type' => '', 'size' => 0, 'alt' => null,
            ]);
            // No library item: shown as the stored path and saved unchanged unless removed.
            $this->state([(string) Str::uuid() => [...$file->toArray(), 'id' => null, 'legacy_path' => $legacy]]);
        }

        return $this;
    }

    private function localeValue(mixed $state): mixed
    {
        $decoded = is_string($state) ? json_decode($state, true) : $state;

        return is_array($decoded)
            ? ($decoded[app()->getLocale()] ?? $decoded['de'] ?? $decoded['en'] ?? null)
            : $state;
    }

    /** Stores the value for the current locale and keeps the other locales of the record. */
    private function mergeLocale(mixed $value): ?string
    {
        $stored = $this->getRecord()?->getRawOriginal($this->getName());
        $decoded = is_string($stored) ? json_decode($stored, true) : null;
        // A plain path was the icon of every locale.
        $values = is_array($decoded) ? $decoded : (filled($stored) ? ['de' => $stored, 'en' => $stored] : []);
        $values[app()->getLocale()] = $value;
        $values = array_filter($values, fn ($v) => filled($v));

        return $values === [] ? null : json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
