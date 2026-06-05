<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MetricValueResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'period_key' => $this->whenLoaded('timePeriod', fn ($timePeriod) => $timePeriod->period_key),
            'label' => $this->whenLoaded('timePeriod', fn ($timePeriod) => $timePeriod->label),
            'value' => (float) $this->value,
        ];
    }
}
