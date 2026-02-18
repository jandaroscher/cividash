<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryGroupRequest extends FormRequest
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
            'key' => ['required', 'string', 'max:255'],
            'title' => ['required', 'array'],
            'title.de' => ['required', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_filterable' => ['nullable', 'boolean'],
            'is_color_source' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'selection_type' => ['nullable', 'string', 'in:single,multi'],
            // tenant_id is explicitly NOT allowed - it comes from context
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tenant_id')) {
            $this->getInputSource()->remove('tenant_id');
        }
    }
}
