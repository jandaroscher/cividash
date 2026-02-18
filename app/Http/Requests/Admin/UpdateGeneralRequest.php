<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'site_name' => ['sometimes', 'string', 'max:255'],
            'site_active' => ['sometimes', 'boolean'],
            'favicon' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
