<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MetricResource extends JsonResource
{
    /**
     * Transform the metric into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string,mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'    => $this->id,
            'label' => $this->label,
            'value' => (float) $this->value,
            'unit'  => $this->unit,
            'icon'  => $this->icon,
        ];
    }
}
