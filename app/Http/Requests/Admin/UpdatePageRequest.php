<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePageRequest extends FormRequest
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
            'title' => ['sometimes', 'array'],
            'title.de' => ['sometimes', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'slug' => ['sometimes', 'array'],
            'slug.de' => ['sometimes', 'string', 'max:255'],
            'slug.en' => ['nullable', 'string', 'max:255'],
            'layout' => ['sometimes', 'nullable', 'string', 'max:255'],
            'blocks' => ['sometimes', 'nullable', 'array'],
            'blocks.de' => ['nullable', 'array'],
            'blocks.en' => ['nullable', 'array'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('pages', 'id')->where('tenant_id', $tenantId),
            ],
            'is_public' => ['sometimes', 'boolean'],
            'meta_title' => ['sometimes', 'nullable', 'array'],
            'meta_title.de' => ['nullable', 'string', 'max:255'],
            'meta_title.en' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'array'],
            'meta_description.de' => ['nullable', 'string'],
            'meta_description.en' => ['nullable', 'string'],
            'meta_image' => ['sometimes', 'nullable', 'string', 'max:255'],
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
