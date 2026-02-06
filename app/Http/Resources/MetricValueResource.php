<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MetricValueResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'year' => $this->whenLoaded('tileYear') ? $this->tileYear->year : null,
            'value' => (float) $this->value,
        ];
    }
}
