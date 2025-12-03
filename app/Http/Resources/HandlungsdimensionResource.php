<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class HandlungsdimensionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request Request that may contain an optional `locale` query parameter for localization.
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $request->query('locale');

        return [
            'id' => $this->id,
            'key' => $this->key,
            'title' => $locale
                ? $this->getTranslation('title', $locale)
                : $this->getTranslations('title'),
            'icon' => $this->icon ? Storage::disk('public')->url($this->icon) : null,
            'position' => $this->position,
            'color' => $this->color,
        ];
    }
}
