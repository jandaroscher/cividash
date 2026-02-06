<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TileYearResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'metrics' => MetricResource::collection(
                $this->whenLoaded('metrics')
            ),
            'metric_values' => MetricValueResource::collection(
                $this->whenLoaded('metricValues')
            ),
        ];
    }
}
