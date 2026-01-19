<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTileYearRequest extends FormRequest
{
    /**
     * Determine whether the incoming request is authorized for this action.
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
            'tile_id' => [
                'required',
                'integer',
                Rule::exists('tiles', 'id')->where('tenant_id', $tenantId),
            ],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            // tenant_id is explicitly NOT allowed - it comes from context
        ];
    }

    /**
     * Remove `tenant_id` from the incoming input before validation.
     *
     * Prevents clients from supplying or modifying `tenant_id` by removing it
     * from the request's input source when present.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('tenant_id')) {
            $this->getInputSource()->remove('tenant_id');
        }
    }
}