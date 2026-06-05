<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TimePeriodResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'period_key' => $this->period_key,
            'granularity' => $this->granularity,
            'label' => $this->label,
            'metrics' => MetricResource::collection(
                $this->whenLoaded('metrics')
            ),
            'metric_values' => MetricValueResource::collection(
                $this->whenLoaded('metricValues')
            ),
        ];
    }
}
