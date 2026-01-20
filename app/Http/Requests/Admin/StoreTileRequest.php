<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTileRequest extends FormRequest
{
    /**
     * Determine whether the request is authorized.
     *
     * @return bool `true` if the request is authorized, `false` otherwise.
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
            'title' => ['required', 'array'],
            'title.de' => ['required', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'array'],
            'slug.de' => ['nullable', 'string', 'max:255'],
            'slug.en' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.de' => ['nullable', 'string'],
            'description.en' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0'],
            'background_blocks' => ['nullable', 'array'],
            'is_public' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'array'],
            'meta_title.de' => ['nullable', 'string', 'max:255'],
            'meta_title.en' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'array'],
            'meta_description.de' => ['nullable', 'string'],
            'meta_description.en' => ['nullable', 'string'],
            'meta_image' => ['nullable', 'string', 'max:255'],
            'handlungsdimension_id' => [
                'nullable',
                'integer',
                Rule::exists('handlungsdimensionen', 'id')->where('tenant_id', $tenantId),
            ],
            // tenant_id is explicitly NOT allowed - it comes from context
        ];
    }

    /**
     * Remove `tenant_id` from the request input before validation.
     *
     * This ensures callers cannot set or override the tenant context by supplying a `tenant_id`
     * in the incoming payload.
     */
    protected function prepareForValidation(): void
    {
        // Remove tenant_id if someone tries to pass it
        if ($this->has('tenant_id')) {
            $this->getInputSource()->remove('tenant_id');
        }
    }
}