<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BrandingSettings extends Settings
{
    /**
     * Fixed --font-size-base values per font_scale option.
     * "default" matches today's hardcoded 1rem default exactly.
     */
    public const FONT_FAMILY_PATTERN = '/^[A-Za-z0-9 ,\'"-]+$/';

    public const FONT_SRC_PATTERN = '/^(?!.*\.\.)[\w\-\/.:%?&=+~#@]+\.(woff2?|WOFF2?)$/';

    public const FONT_SCALE_SIZES = [
        'compact' => '0.875rem',
        'default' => '1rem',
        'large' => '1.125rem',
    ];

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

    public ?string $header_background_color;

    public ?string $footer_background_color;

    public ?string $text_primary_color;

    public ?string $text_secondary_color;

    public ?string $text_inverse_color;

    public ?string $link_color;

    public ?string $link_hover_color;

    public ?string $border_color;

    public ?string $divider_color;

    public ?string $shadow_color;

    public ?string $nav_text_color;

    public ?string $nav_text_color_inactive;

    public ?string $nav_hover_color;

    public ?array $typography_font_sizes;

    public ?string $typography_custom_font_name;

    public ?string $typography_custom_font_file;

    public ?int $tile_color_source_group_id;

    public ?int $tile_background_category_group_id;

    // Structural card tokens: CSS length
    // values (e.g. "0.5rem", "2px"), nullable so existing tenants fall back to
    // the CSS defaults in app.css without a migration data backfill.
    public ?string $card_radius;

    public ?string $card_border_width;

    public ?string $card_border_color;

    // Font schema: separate heading/body font
    // stacks, a base-size scale, and self-hosted font faces. Nullable /
    // "default" so existing tenants keep today's single-font rendering.
    public ?string $font_family_heading;

    public ?string $font_family_body;

    public string $font_scale = 'default';

    public array $font_faces = [];

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
        'font_faces' => 'array',
    ];
}
