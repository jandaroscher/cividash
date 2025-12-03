<?php

namespace App\Http\Resources;

use App\Services\Content\BlockTransformer;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TileResource extends JsonResource
{
    /**
     * Transform the resource into an array for JSON responses.
     *
     * Returns an associative array containing:
     * - `id`: tile identifier
     * - `categories`: collection of category slugs
     * - `title` / `description`: either all translations or a single locale's translation when the `locale` query parameter is provided
     * - `icon`: public URL for the icon or `null`
     * - `background_blocks`: transformed blocks in API format or `null`
     * - `years`: collection of related years via TileYearResource when the relation is loaded
     *
     * @param \Illuminate\Http\Request $request Incoming request (reads optional `locale` query parameter).
     * @return array The resource represented as an associative array for JSON serialization.
     */
    public function toArray($request): array
    {
        $locale = $request->query('locale');
        $blockTransformer = new BlockTransformer();

        return [
            'id'         => $this->id,

            // Handlungsfelder (same as categories, but using resource)
            'handlungsfelder' => HandlungsfeldResource::collection(
                $this->relationLoaded('handlungsfelder') ? $this->handlungsfelder : $this->categories
            ),

            // Handlungsdimension
            'handlungsdimension' => $this->when(
                $this->relationLoaded('handlungsdimension') && $this->handlungsdimension,
                fn () => new HandlungsdimensionResource($this->handlungsdimension)
            ),

            // SDG-Ziele
            'sdg_ziele' => SDGZielResource::collection(
                $this->whenLoaded('sdgZiele')
            ),

            // Title & description: all or single
            'title'       => $locale
                ? $this->getTranslation('title', $locale)
                : $this->getTranslations('title'),

            'description' => $locale
                ? $this->getTranslation('description', $locale)
                : $this->getTranslations('description'),

            'icon'        => $this->icon
                ? Storage::disk('public')->url($this->icon)
                : null,

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
}