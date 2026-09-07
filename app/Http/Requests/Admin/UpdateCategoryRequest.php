<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->attributes->get('resolved_tenant')?->id;

        return [
            'category_group_id' => [
                'sometimes',
                'integer',
                Rule::exists('category_groups', 'id')->where('tenant_id', $tenantId),
            ],
            'slug' => ['sometimes', 'array'],
            'slug.de' => ['sometimes', 'string', 'max:255'],
            'slug.en' => ['nullable', 'string', 'max:255'],
            'key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
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
