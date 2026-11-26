<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Http\Resources;

use App\Services\Media\MediaLibrary;
use Illuminate\Http\Resources\Json\JsonResource;

class MetricDefinitionResource extends JsonResource
{
    public function toArray($request): array
    {
        $locale = $request->query('locale');

        return [
            'id' => $this->id,
            'metric_key' => $this->metric_key,
            'label' => $locale
                ? $this->getTranslation('label', $locale)
                : $this->getTranslations('label'),
            'unit' => $locale
                ? ($this->unit ? $this->getTranslation('unit', $locale) : null)
                : ($this->unit ? $this->getTranslations('unit') : null),
            'icon' => app(MediaLibrary::class)->url($this->icon),
            'indicator_type' => $this->indicator_type ?? 'small',
            'values' => MetricValueResource::collection(
                $this->whenLoaded('metricValues', function () {
                    $values = $this->metricValues;

                    if (! $values) {
                        return $values;
                    }

                    return $values->filter(fn ($value) => $value->is_active ?? true);
                })
            ),
        ];
    }
}
