<?php

namespace App\Http\Requests\Admin;

use App\Models\TimePeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTimePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $timePeriod = $this->resolveTimePeriod();

        return [
            'period_key' => [
                'sometimes',
                'string',
                'max:20',
                $timePeriod ? Rule::unique('time_periods')
                    ->where('tile_id', $timePeriod->tile_id)
                    ->ignore($timePeriod->id) : '',
            ],
            'label' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tenant_id')) {
            $this->getInputSource()->remove('tenant_id');
        }
    }

    /**
     * Resolve the TimePeriod being updated from the route id.
     *
     * Returns null when there is no route id (e.g. during API-doc extraction,
     * where Scribe instantiates the FormRequest without a real request) or
     * when the lookup cannot run. This keeps rules() from throwing and only
     * applies the DB-dependent uniqueness rule when an actual record exists.
     */
    private function resolveTimePeriod(): ?TimePeriod
    {
        $id = $this->route('id');

        if ($id === null) {
            return null;
        }

        try {
            return TimePeriod::find($id);
        } catch (\Throwable) {
            return null;
        }
    }
}
