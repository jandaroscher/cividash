<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MetricValueResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'period_key' => $this->whenLoaded('timePeriod', fn ($timePeriod) => $timePeriod->period_key),
            'label' => $this->whenLoaded('timePeriod', fn ($timePeriod) => $timePeriod->label),
            'value' => $this->value !== null ? (float) $this->value : null,
        ];
    }
}
