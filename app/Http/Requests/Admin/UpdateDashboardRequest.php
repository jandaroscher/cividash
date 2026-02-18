<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'open_source_docs_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'user_manual_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'contact_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'made_with_text' => ['sometimes', 'nullable', 'string', 'max:500'],
            'show_server_time' => ['sometimes', 'boolean'],
        ];
    }
}
