<?php

namespace App\Http\Resources;

use App\Services\Content\BlockTransformer;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TileResource extends JsonResource
{
    /**
     * Convert the Tile resource into an associative array suitable for JSON responses.
     *
     * The output includes identifier, dynamic categories (when loaded), a resolved tile color,
     * title/description/slug and meta fields which are either full translations or a single
     * locale translation when the request `locale` query parameter is provided, public URLs
     * for stored images when present, transformed background blocks, metric definitions and years
     * (each included only when their relations are loaded).
     *
     * @param  \Illuminate\Http\Request  $request  Incoming HTTP request (reads optional `locale` query parameter).
     * @return array Associative array representation of the tile containing keys: `id`, `categories`, `tile_color`,
     *               `title`, `description`, `slug`, `icon`, `is_public`, `meta` (with `title`, `description`, `image`),
     *               `background_blocks`, `metric_definitions`, and `years`.
     */
    public function toArray($request): array
    {
        $locale = $request->query('locale');
        $blockTransformer = new BlockTransformer;

        return [
            'id' => $this->id,

            // Dynamic categories grouped by parent
            'categories' => CategoryItemResource::collection(
                $this->whenLoaded('categories')
            ),
            'tile_color' => $this->resolveTileColor(),

            // Title & description: all or single
            'title' => $locale
                ? $this->getTranslation('title', $locale)
                : $this->getTranslations('title'),

            'description' => $locale
                ? $this->getTranslation('description', $locale)
                : $this->getTranslations('description'),

            'slug' => $locale
                ? $this->getTranslation('slug', $locale)
                : $this->getTranslations('slug'),

            'icon' => $this->icon
                ? Storage::disk('public')->url($this->icon)
                : null,

            'is_public' => $this->is_public,

            'meta' => [
                'title' => $locale
                    ? $this->getTranslation('meta_title', $locale)
                    : $this->getTranslations('meta_title'),
                'description' => $locale
                    ? $this->getTranslation('meta_description', $locale)
                    : $this->getTranslations('meta_description'),
                'image' => $this->meta_image
                    ? Storage::disk('public')->url($this->meta_image)
                    : null,
            ],

            // Background blocks: transform from Filament Builder format to API format
            'background_blocks' => $this->background_blocks
                ? $blockTransformer->transform($this->background_blocks)
                : null,

            // Metric definitions: new structure (Option B)
            'metric_definitions' => MetricDefinitionResource::collection(
                $this->whenLoaded('metricDefinitions')
            ),

            // Years: delegate to their Resources
            'years' => TileYearResource::collection(
                $this->whenLoaded('tileYears')
            ),
        ];
    }

    /**
     * Selects the tile color from the first category whose loaded group is marked as the color source.
     *
     * Only inspects categories when the `categories` relation is loaded; returns null if no matching category or relation is not loaded.
     *
     * @return string|null The color value from the matching category, or `null` if none is found.
     */
    protected function resolveTileColor(): ?string
    {
        if (! $this->relationLoaded('categories')) {
            return null;
        }

        foreach ($this->categories as $category) {
            if ($category->relationLoaded('group') && $category->group?->is_color_source) {
                return $category->color;
            }
        }

        return null;
    }
}
