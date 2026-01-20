<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTileRequest extends FormRequest
{
    /**
     * Allow the request; authorization is handled by middleware.
     *
     * @return bool `true` to allow the request, `false` to deny it.
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
            'title' => ['sometimes', 'array'],
            'title.de' => ['sometimes', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'array'],
            'slug.de' => ['nullable', 'string', 'max:255'],
            'slug.en' => ['nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'array'],
            'description.de' => ['nullable', 'string'],
            'description.en' => ['nullable', 'string'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'background_blocks' => ['sometimes', 'nullable', 'array'],
            'is_public' => ['sometimes', 'boolean'],
            'meta_title' => ['sometimes', 'nullable', 'array'],
            'meta_title.de' => ['nullable', 'string', 'max:255'],
            'meta_title.en' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'array'],
            'meta_description.de' => ['nullable', 'string'],
            'meta_description.en' => ['nullable', 'string'],
            'meta_image' => ['sometimes', 'nullable', 'string', 'max:255'],
            'handlungsdimension_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('handlungsdimensionen', 'id')->where('tenant_id', $tenantId),
            ],
            // tenant_id is explicitly NOT allowed - it comes from context and cannot be changed
        ];
    }

    /**
     * Prepare the data for validation.
     * Strips tenant_id from input to prevent tampering.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('tenant_id')) {
            $this->getInputSource()->remove('tenant_id');
        }
    }
}