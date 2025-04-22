<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'description'    => $this->description,
            'icon'           => $this->icon,
            'position'       => $this->position,
            'categories'     => $this->categories->pluck('slug'),
            // use the new resource here
            'backgroundPage' => new BackgroundPageResource(
                $this->whenLoaded('backgroundPage')
            ),
            'years'          => TileYearResource::collection(
                $this->whenLoaded('tileYears')
            ),
        ];
    }
}
