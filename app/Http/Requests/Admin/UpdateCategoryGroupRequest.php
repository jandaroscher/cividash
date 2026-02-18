<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'key' => ['sometimes', 'string', 'max:255'],
            'title' => ['sometimes', 'array'],
            'title.de' => ['sometimes', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_filterable' => ['sometimes', 'boolean'],
            'is_color_source' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'selection_type' => ['sometimes', 'nullable', 'string', 'in:single,multi'],
            // tenant_id is explicitly NOT allowed - it comes from context and cannot be changed
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tenant_id')) {
            $this->getInputSource()->remove('tenant_id');
        }
    }
}
