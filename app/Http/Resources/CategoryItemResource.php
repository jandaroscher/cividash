<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CategoryItemResource extends JsonResource
{
    /**
     * Convert the category item resource to an array for JSON responses.
     *
     * The returned array contains id, key, title (locale-aware), icon (public URL or localized set of URLs),
     * position, color, and a conditional `group` block included only when the relationship is loaded.
     *
     * @param  Request  $request  Request instance; the optional `locale` query parameter selects translations for `title` and `group.title`.
     * @return array<string,mixed> Associative array with keys:
     *                             - `id`: resource identifier
     *                             - `key`: resource key
     *                             - `title`: localized title string or array of translations
     *                             - `icon`: public URL string, localized set of URLs, or null
     *                             - `position`: resource position
     *                             - `color`: resource color
     *                             - `group` (when loaded): array with `id`, `key`, `title`, `selection_type`, `is_color_source`, and `is_filterable`
     */
    public function toArray(Request $request): array
    {
        $locale = $request->query('locale');
        $title = $locale
            ? $this->getTranslation('slug', $locale)
            : $this->getTranslations('slug');

        return [
            'id' => $this->id,
            'key' => $this->key,
            'title' => $title,
            'icon' => $this->resolveIcon($locale),
            'position' => $this->position,
            'color' => $this->color,
            'group' => $this->whenLoaded('group', function ($group) use ($locale) {
                if (! $group) {
                    return null;
                }

                $groupTitle = $locale
                    ? $group->getTranslation('title', $locale)
                    : $group->getTranslations('title');

                return [
                    'id' => $group->id,
                    'key' => $group->key,
                    'title' => $groupTitle,
                    'selection_type' => $group->selection_type,
                    'is_color_source' => $group->is_color_source,
                    'is_filterable' => $group->is_filterable,
                ];
            }),
        ];
    }

    /**
     * Resolve icon paths into public URLs and select a locale-specific icon when applicable.
     *
     * @param  string|null  $locale  Locale code used to pick a localized icon entry when the stored icon is translatable.
     * @return array<string,string>|string|null An array of transformed public URLs keyed by locale or name, a single public URL string, or `null` if no icon is available.
     */
    protected function resolveIcon(?string $locale)
    {
        $iconValue = $this->icon;

        if (is_string($iconValue)) {
            $decoded = json_decode($iconValue, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $iconValue = $decoded;
            }
        }

        if (is_array($iconValue)) {
            if ($locale) {
                $iconValue = $iconValue[$locale] ?? $iconValue['de'] ?? $iconValue['en'] ?? null;
            }
        }

        if (is_string($iconValue)) {
            return $iconValue ? Storage::disk('public')->url($iconValue) : null;
        }

        if (is_array($iconValue)) {
            foreach ($iconValue as $key => $path) {
                if ($path) {
                    $iconValue[$key] = Storage::disk('public')->url($path);
                }
            }
        }

        return $iconValue ?: null;
    }
}
