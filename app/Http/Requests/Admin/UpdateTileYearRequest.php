<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTileYearRequest extends FormRequest
{
    /**
     * Indicate whether the current user is permitted to perform this request.
     *
     * Authorization is enforced by middleware; this method always allows the request.
     *
     * @return bool `true` to allow the request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Validation rules for a partial update of a TileYear; all fields are optional.
     *
     * The returned array maps request field names to their validation constraints. `tenant_id` is intentionally excluded and must not be provided.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string> Mapping of field names to validation rules.
     */
    public function rules(): array
    {
        $tenantId = $this->attributes->get('resolved_tenant')?->id;

        return [
            'tile_id' => [
                'sometimes',
                'integer',
                Rule::exists('tiles', 'id')->where('tenant_id', $tenantId),
            ],
            'year' => ['sometimes', 'integer', 'min:1900', 'max:2100'],
            // tenant_id is explicitly NOT allowed - it comes from context and cannot be changed
        ];
    }

    /**
     * Remove `tenant_id` from the request input before validation.
     *
     * If `tenant_id` is present in the incoming data, it is removed to prevent
     * clients from tampering with or overriding the tenant context.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('tenant_id')) {
            $this->getInputSource()->remove('tenant_id');
        }
    }
}