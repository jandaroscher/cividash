<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMetricValueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     * All fields are optional for PATCH (partial update).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->attributes->get('resolved_tenant')?->id;

        return [
            'metric_definition_id' => [
                'sometimes',
                'integer',
                Rule::exists('metric_definitions', 'id')->where('tenant_id', $tenantId),
            ],
            'time_period_id' => [
                'sometimes',
                'integer',
                Rule::exists('time_periods', 'id')->where('tenant_id', $tenantId),
            ],
            'value' => ['sometimes', 'numeric'],
            'is_active' => ['sometimes', 'boolean'],
            // tenant_id is explicitly NOT allowed - it comes from context and cannot be changed
        ];
    }

    /**
     * Remove `tenant_id` from the request input before validation to prevent clients from modifying it.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('tenant_id')) {
            $this->getInputSource()->remove('tenant_id');
        }
    }
}
