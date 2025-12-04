<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BrandingSettings extends Settings
{

    public string $primary_color;
    public string $secondary_color;
    public ?string $logo_url;
    public ?string $accent_color;
    public ?string $typography_font_family;
    public ?array $typography_font_weights;
    public ?array $slider_colors;
    public ?string $background_color;
    public ?string $card_background_color;
    public ?string $hero_background_color;
    public ?string $overlay_background_color;
    public ?string $text_primary_color;
    public ?string $text_secondary_color;
    public ?string $text_inverse_color;
    public ?string $link_color;
    public ?string $link_hover_color;
    public ?string $border_color;
    public ?string $divider_color;
    public ?string $shadow_color;
    public ?array $typography_font_sizes;
    public ?string $typography_custom_font_name;
    public ?string $typography_custom_font_file;

    /**
     * Get the settings group name for branding.
     *
     * @return string The settings group key "branding".
     */
    public static function group(): string
    {
        return 'branding';
    }

    // Cast JSON strings to arrays
    protected $casts = [
        'typography_font_weights' => 'array',
        'slider_colors' => 'array',
        'typography_font_sizes' => 'array',
    ];
}