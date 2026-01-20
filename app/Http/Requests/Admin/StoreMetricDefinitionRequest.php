<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMetricDefinitionRequest extends FormRequest
{
    /**
     * Allow the incoming request; authorization is enforced by middleware.
     *
     * @return bool `true` if the request is authorized, `false` otherwise. This implementation always returns `true`.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->attributes->get('resolved_tenant')?->id;

        return [
            'tile_id' => [
                'required',
                'integer',
                Rule::exists('tiles', 'id')->where('tenant_id', $tenantId),
            ],
            'metric_key' => ['required', 'string', 'max:255'],
            'label' => ['required', 'array'],
            'label.de' => ['required', 'string', 'max:255'],
            'label.en' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'array'],
            'unit.de' => ['nullable', 'string', 'max:50'],
            'unit.en' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:255'],
            'indicator_type' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            // tenant_id is explicitly NOT allowed - it comes from context
        ];
    }

    /**
     * Remove `tenant_id` from the request input before validation.
     *
     * Ensures the tenant identifier cannot be supplied or modified by the client.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('tenant_id')) {
            $this->getInputSource()->remove('tenant_id');
        }
    }
}