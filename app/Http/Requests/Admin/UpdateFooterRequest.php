<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFooterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'footer_navigation_items' => ['sometimes', 'array'],
            'footer_navigation_items.de' => ['sometimes', 'nullable', 'array'],
            'footer_navigation_items.en' => ['sometimes', 'nullable', 'array'],
            'social_links' => ['sometimes', 'array'],
            'social_links.de' => ['sometimes', 'nullable', 'array'],
            'social_links.en' => ['sometimes', 'nullable', 'array'],
            'layout_type' => ['sometimes', 'string', 'in:single-row,multi-column,grid'],
            'columns' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'social_links_enabled' => ['sometimes', 'boolean'],
            'copyright_text' => ['sometimes', 'array'],
            'copyright_text.de' => ['sometimes', 'nullable', 'string'],
            'copyright_text.en' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
