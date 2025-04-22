<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TileYearResource extends JsonResource
{
    /**
     * Transform the tile‑year group into an array.
     */
    public function toArray($request): array
    {
        return [
            'id'      => $this->id,
            'year'    => $this->year,
            'metrics' => MetricResource::collection(
                $this->whenLoaded('metrics')
            ),
        ];
    }
}
