<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMetricValueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Define the validation rules for storing a metric value.
     *
     * Validates that `metric_definition_id` and `tile_year_id` are required integers that exist
     * in their respective tables, and that `value` is required and numeric. `tenant_id` is not
     * accepted from input and is provided by request context.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string> Validation rules keyed by field name.
     */
    public function rules(): array
    {
        $tenantId = $this->attributes->get('resolved_tenant')?->id;

        return [
            'metric_definition_id' => [
                'required',
                'integer',
                Rule::exists('metric_definitions', 'id')->where('tenant_id', $tenantId),
            ],
            'tile_year_id' => [
                'required',
                'integer',
                Rule::exists('tile_years', 'id')->where('tenant_id', $tenantId),
            ],
            'value' => ['required', 'numeric'],
            'is_active' => ['sometimes', 'boolean'],
            // tenant_id is explicitly NOT allowed - it comes from context
        ];
    }

    /**
     * Remove any provided `tenant_id` from the request input before validation to prevent tampering.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('tenant_id')) {
            $this->getInputSource()->remove('tenant_id');
        }
    }
}
