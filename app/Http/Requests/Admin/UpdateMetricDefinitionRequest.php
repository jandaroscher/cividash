<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMetricDefinitionRequest extends FormRequest
{
    /**
     * Allow the request to proceed; authorization is enforced by middleware.
     *
     * @return bool `true` to allow the request to proceed, `false` otherwise.
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
            'tile_id' => [
                'sometimes',
                'integer',
                Rule::exists('tiles', 'id')->where('tenant_id', $tenantId),
            ],
            'metric_key' => ['sometimes', 'string', 'max:255'],
            'label' => ['sometimes', 'array'],
            'label.de' => ['sometimes', 'string', 'max:255'],
            'label.en' => ['nullable', 'string', 'max:255'],
            'unit' => ['sometimes', 'array'],
            'unit.de' => ['nullable', 'string', 'max:50'],
            'unit.en' => ['nullable', 'string', 'max:50'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'indicator_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            // tenant_id is explicitly NOT allowed - it comes from context and cannot be changed
        ];
    }

    /**
     * Remove `tenant_id` from the incoming input to prevent clients from changing tenant context.
     *
     * Executed before validation; if `tenant_id` is present in the request data it will be removed so it cannot be validated or persisted.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('tenant_id')) {
            $this->getInputSource()->remove('tenant_id');
        }
    }
}
