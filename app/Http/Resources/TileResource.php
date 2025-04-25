<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TileResource extends JsonResource
{
    public function toArray($request): array
    {
        $locale = $request->query('locale');

        return [
            'id'         => $this->id,
            'categories' => $this->categories->pluck('slug'),

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

            // BackgroundPage: same pattern
            'backgroundPage' => $this->backgroundPage
                ? [
                    'slug'    => $locale
                        ? $this->backgroundPage->getTranslation('slug', $locale)
                        : $this->backgroundPage->getTranslations('slug'),
                    'content' => $locale
                        ? $this->backgroundPage->getTranslation('content', $locale)
                        : $this->backgroundPage->getTranslations('content'),
                ]
                : null,

            // Years & Metrics: delegate to their Resources
            'years' => TileYearResource::collection(
                $this->whenLoaded('tileYears')
            ),
        ];
    }
}
