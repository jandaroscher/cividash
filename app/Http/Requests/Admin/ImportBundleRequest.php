<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportBundleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware (auth:sanctum + admin.api).
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:json,txt', 'max:20480'], // max 20 MB
            'mode' => ['required', 'string', 'in:dry_run,commit'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Eine Datei muss hochgeladen werden.',
            'file.mimes' => 'Nur JSON-Dateien werden unterstützt (MIME application/json oder text/plain).',
            'file.max' => 'Die Datei darf maximal 20 MB groß sein.',
            'mode.in' => 'mode muss "dry_run" oder "commit" sein.',
        ];
    }
}
