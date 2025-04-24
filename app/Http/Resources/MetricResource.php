<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MetricResource extends JsonResource
{
    public function toArray($request): array
    {
        $locale = $request->query('locale');

        return [
            'id'    => $this->id,
            'label' => $locale
                ? $this->getTranslation('label', $locale)
                : $this->getTranslations('label'),
            'value' => (float) $this->value,
            'unit'  => $locale
                ? $this->getTranslation('unit', $locale)
                : $this->getTranslations('unit'),
            'icon'  => $this->icon,
        ];
    }
}
