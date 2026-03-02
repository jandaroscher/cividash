<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNavigationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'navigation_items' => ['sometimes', 'array'],
            'navigation_items.de' => ['sometimes', 'nullable', 'array'],
            'navigation_items.en' => ['sometimes', 'nullable', 'array'],
            'dropdown_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
