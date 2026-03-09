<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryGroupResource extends JsonResource
{
    /**
     * Convert the category group resource into an array for serialization.
     *
     * The returned array includes the resource's id, key, title (localized when
     * the request contains a `locale` query parameter; otherwise all title
     * translations), position, and related category items when the `categories`
     * relation is loaded.
     *
     * @param  Request  $request  Request instance; may include optional `locale` query parameter to select a single title translation.
     * @return array<string, mixed> Associative array representation of the category group resource.
     */
    public function toArray(Request $request): array
    {
        $locale = $request->query('locale');
        $title = $locale
            ? $this->getTranslation('title', $locale)
            : $this->getTranslations('title');

        return [
            'id' => $this->id,
            'key' => $this->key,
            'title' => $title,
            'position' => $this->position,
            'items' => CategoryItemResource::collection($this->whenLoaded('categories')),
        ];
    }
}
