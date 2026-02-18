<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
        $tenantId = $this->attributes->get('resolved_tenant')?->id;

        return [
            'category_group_id' => [
                'required',
                'integer',
                Rule::exists('category_groups', 'id')->where('tenant_id', $tenantId),
            ],
            'slug' => ['required', 'array'],
            'slug.de' => ['required', 'string', 'max:255'],
            'slug.en' => ['nullable', 'string', 'max:255'],
            'key' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
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
