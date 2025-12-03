<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MetricDefinitionResource extends JsonResource
{
    public function toArray($request): array
    {
        $locale = $request->query('locale');

        return [
            'id'             => $this->id,
            'metric_key'     => $this->metric_key,
            'label'          => $locale
                ? $this->getTranslation('label', $locale)
                : $this->getTranslations('label'),
            'unit'           => $locale
                ? ($this->unit ? $this->getTranslation('unit', $locale) : null)
                : ($this->unit ? $this->getTranslations('unit') : null),
            'icon'           => $this->icon
                ? Storage::disk('public')->url($this->icon)
                : null,
            'indicator_type' => $this->indicator_type ?? 'small',
            'values'         => MetricValueResource::collection(
                $this->whenLoaded('metricValues')
            ),
        ];
    }
}

