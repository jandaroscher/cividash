<?php

namespace App\Http\Requests\Admin;

use App\Enums\TimeGranularity;
use App\Models\Tile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->attributes->get('resolved_tenant')?->id;

        return [
            'tile_id' => [
                'required',
                'integer',
                Rule::exists('tiles', 'id')->where('tenant_id', $tenantId),
            ],
            'period_key' => [
                'required',
                'string',
                'max:20',
                function ($attribute, $value, $fail) {
                    $tile = Tile::find($this->input('tile_id'));
                    if (! $tile) {
                        return;
                    }

                    $granularity = TimeGranularity::tryFrom($tile->time_granularity ?? 'year');
                    if ($granularity && ! preg_match($granularity->periodKeyPattern(), $value)) {
                        $fail(__('validation.custom.period_key.format', [
                            'example' => $granularity->periodKeyPlaceholder(),
                        ]));
                    }
                },
                Rule::unique('time_periods')->where('tile_id', $this->input('tile_id')),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tenant_id')) {
            $this->getInputSource()->remove('tenant_id');
        }
    }
}
