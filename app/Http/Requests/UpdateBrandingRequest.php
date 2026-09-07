<?php

namespace App\Http\Requests;

use App\Settings\BrandingSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandingRequest extends FormRequest
{
    /**
     * Determine whether the current request has an authenticated user.
     *
     * @return bool `true` if a user is present, `false` otherwise.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get validation rules for updating branding settings.
     *
     * @return array<string, ValidationRule|array<mixed>|string> An associative array mapping request input field names to their validation rules.
     */
    public function rules(): array
    {
        return [
            'primary_color' => ['sometimes', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'secondary_color' => ['sometimes', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'logo_url' => ['nullable', 'string', 'max:500', 'regex:/^(?!.*\.\.)[\w\-\/.:%?&=+~#@]+$/'],
            'typography_font_family' => ['sometimes', 'string', 'max:255'],
            'typography_font_weights' => ['sometimes', 'array'],
            'typography_font_weights.*' => ['integer', 'min:100', 'max:900'],
            'slider_colors' => ['sometimes', 'array'],
            'slider_colors.rail' => ['sometimes', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'slider_colors.handle' => ['sometimes', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'slider_colors.handleBorder' => ['sometimes', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'background_color' => ['nullable', 'string'],
            'card_background_color' => ['nullable', 'string', 'regex:/^(#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})|rgba?\([^)]+\))$/'],
            'hero_background_color' => ['nullable', 'string', 'regex:/^(#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})|rgba?\([^)]+\))$/'],
            'overlay_background_color' => ['nullable', 'string', 'regex:/^(#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})|rgba?\([^)]+\))$/'],
            'header_background_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'footer_background_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'text_primary_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'text_secondary_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'text_inverse_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'link_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'link_hover_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'border_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'divider_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'shadow_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'nav_text_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'nav_text_color_inactive' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'nav_hover_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'typography_font_sizes' => ['sometimes', 'array'],
            'typography_font_sizes.*' => ['string', 'regex:/^[\d.]+(rem|px)$/'],
            'typography_custom_font_name' => ['nullable', 'string', 'max:255'],
            'typography_custom_font_file' => ['nullable', 'string', 'max:500', 'regex:/^(?!.*\.\.)[\w\-\/.:%?&=+~#@]+$/'],
            'font_family_heading' => ['nullable', 'string', 'max:255', 'regex:'.BrandingSettings::FONT_FAMILY_PATTERN],
            'font_family_body' => ['nullable', 'string', 'max:255', 'regex:'.BrandingSettings::FONT_FAMILY_PATTERN],
            'font_scale' => ['sometimes', Rule::in(['compact', 'default', 'large'])],
            'font_faces' => ['sometimes', 'array'],
            'font_faces.*.family' => ['required_with:font_faces', 'string', 'max:255', 'regex:'.BrandingSettings::FONT_FAMILY_PATTERN],
            'font_faces.*.src' => ['required_with:font_faces', 'string', 'max:500', 'regex:'.BrandingSettings::FONT_SRC_PATTERN],
            'font_faces.*.weight' => ['nullable', 'integer', 'min:100', 'max:900'],
            'font_faces.*.style' => ['nullable', 'string', Rule::in(['normal', 'italic'])],
            'tile_color_source_group_id' => [
                'nullable',
                'integer',
                Rule::exists('category_groups', 'id')
                    ->where('tenant_id', $this->attributes->get('resolved_tenant')?->id)
                    ->where('is_active', true),
            ],
            'tile_background_category_group_id' => [
                'nullable',
                'integer',
                Rule::exists('category_groups', 'id')
                    ->where('tenant_id', $this->attributes->get('resolved_tenant')?->id)
                    ->where('is_active', true),
            ],
        ];
    }
}
