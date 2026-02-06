<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SDGZielResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request  Request that may contain an optional `locale` query parameter for localization.
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $request->query('locale');

        // Get icon URL(s) - handle translatable icon field
        $iconValue = $locale
            ? $this->getTranslation('icon', $locale)
            : $this->getTranslations('icon');

        // Convert icon path(s) to full URLs if they exist
        if (is_string($iconValue) && $iconValue) {
            $iconValue = \Illuminate\Support\Facades\Storage::disk('public')->url($iconValue);
        } elseif (is_array($iconValue)) {
            foreach ($iconValue as $key => $path) {
                if ($path) {
                    $iconValue[$key] = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
                }
            }
        }

        return [
            'id' => $this->id,
            'number' => $this->number,
            'title' => $locale
                ? $this->getTranslation('title', $locale)
                : $this->getTranslations('title'),
            'icon' => $iconValue,
            'position' => $this->position,
        ];
    }
}
