<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBrandingRequest;
use App\Settings\BrandingSettings;
use App\Settings\FooterSettings;
use App\Settings\GeneralSettings;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ConfigController extends Controller
{
    /**
     * Retrieve branding settings and return them as a JSON resource.
     *
     * The returned resource contains branding and styling values sourced from BrandingSettings,
     * including color palette, typography, background and overlay colors, text and link colors,
     * border/divider/shadow colors, slider colors, font sizes, and custom font metadata.
     * Stored file paths for `logo_url` and `typography_custom_font_file` are converted to public URLs
     * when present; otherwise those fields are `null`.
     *
     * @return JsonResource JSON resource containing the following keys: `primary_color`, `secondary_color`,
     * `logo_url`, `accent_color`, `typography_font_family`, `typography_font_weights`, `slider_colors`,
     * `background_color`, `card_background_color`, `hero_background_color`, `overlay_background_color`,
     * `header_background_color`, `footer_background_color`, `text_primary_color`, `text_secondary_color`,
     * `text_inverse_color`, `link_color`, `link_hover_color`, `border_color`, `divider_color`, `shadow_color`,
     * `typography_font_sizes`, `typography_custom_font_name`, `typography_custom_font_file`.
     */
    public function branding(): JsonResource
    {
        $settings = app(BrandingSettings::class);

        return new JsonResource([
            'primary_color'   => $settings->primary_color,
            'secondary_color' => $settings->secondary_color,
            // Convert the stored path into a public URL:
            'logo_url'        => $settings->logo_url
                ? Storage::disk('public')->url($settings->logo_url)
                : null,
            'accent_color'    => $settings->accent_color,
            'typography_font_family' => $settings->typography_font_family,
            'typography_font_weights' => $settings->typography_font_weights,
            'slider_colors'   => $settings->slider_colors,
            'background_color' => $settings->background_color,
            'card_background_color' => $settings->card_background_color,
            'hero_background_color' => $settings->hero_background_color,
            'overlay_background_color' => $settings->overlay_background_color,
            'header_background_color' => $settings->header_background_color,
            'footer_background_color' => $settings->footer_background_color,
            'text_primary_color' => $settings->text_primary_color,
            'text_secondary_color' => $settings->text_secondary_color,
            'text_inverse_color' => $settings->text_inverse_color,
            'link_color' => $settings->link_color,
            'link_hover_color' => $settings->link_hover_color,
            'border_color' => $settings->border_color,
            'divider_color' => $settings->divider_color,
            'shadow_color' => $settings->shadow_color,
            'typography_font_sizes' => $settings->typography_font_sizes,
            'typography_custom_font_name' => $settings->typography_custom_font_name,
            'typography_custom_font_file' => $settings->typography_custom_font_file
                ? Storage::disk('public')->url($settings->typography_custom_font_file)
                : null,
        ]);
    }

    public function general(): JsonResource
    {
        $settings = app(GeneralSettings::class);

        return new JsonResource([
            'site_name'   => $settings->site_name,
            'site_active' => $settings->site_active,
        ]);
    }

    /**
     * Retrieve footer configuration settings as a JSON resource.
     *
     * @return \Illuminate\Http\Resources\Json\JsonResource JsonResource containing `footer_links`, `footer_logos`, and `social_links`.
     */
    public function footer(): JsonResource
    {
        $settings = app(FooterSettings::class);

        return new JsonResource([
            'footer_links' => $settings->footer_links,
            'footer_logos' => $settings->footer_logos,
            'social_links' => $settings->social_links,
        ]);
    }

    /**
     * Update stored branding settings with the validated request data and return the updated branding payload.
     *
     * Only keys present in the validated input are persisted. File path fields (`logo_url` and
     * `typography_custom_font_file`) are converted to public URLs when present; otherwise they are `null`.
     *
     * @param UpdateBrandingRequest $request Request containing validated branding fields to update.
     * @return JsonResource Associative array of branding properties (colors, typography, background/header/footer values, text/link/border/divider/shadow colors, font sizes and names), with `logo_url` and `typography_custom_font_file` as public URLs when available, otherwise `null`.
     */
    public function updateBranding(UpdateBrandingRequest $request): JsonResource
    {
        $settings = app(BrandingSettings::class);
        $validated = $request->validated();

        // Update only provided fields
        foreach ($validated as $key => $value) {
            if (property_exists($settings, $key)) {
                $settings->$key = $value;
            }
        }

        $settings->save();

        // Reload settings to get updated values
        $settings = app(BrandingSettings::class);

        // Return updated settings in the same format as GET
        return new JsonResource([
            'primary_color'   => $settings->primary_color,
            'secondary_color' => $settings->secondary_color,
            'logo_url'        => $settings->logo_url
                ? Storage::disk('public')->url($settings->logo_url)
                : null,
            'accent_color'    => $settings->accent_color,
            'typography_font_family' => $settings->typography_font_family,
            'typography_font_weights' => $settings->typography_font_weights,
            'slider_colors'   => $settings->slider_colors,
            'background_color' => $settings->background_color,
            'card_background_color' => $settings->card_background_color,
            'hero_background_color' => $settings->hero_background_color,
            'overlay_background_color' => $settings->overlay_background_color,
            'header_background_color' => $settings->header_background_color,
            'footer_background_color' => $settings->footer_background_color,
            'text_primary_color' => $settings->text_primary_color,
            'text_secondary_color' => $settings->text_secondary_color,
            'text_inverse_color' => $settings->text_inverse_color,
            'link_color' => $settings->link_color,
            'link_hover_color' => $settings->link_hover_color,
            'border_color' => $settings->border_color,
            'divider_color' => $settings->divider_color,
            'shadow_color' => $settings->shadow_color,
            'typography_font_sizes' => $settings->typography_font_sizes,
            'typography_custom_font_name' => $settings->typography_custom_font_name,
            'typography_custom_font_file' => $settings->typography_custom_font_file
                ? Storage::disk('public')->url($settings->typography_custom_font_file)
                : null,
        ]);
    }
}