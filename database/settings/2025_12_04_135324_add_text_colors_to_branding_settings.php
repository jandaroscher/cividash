<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Register five nullable branding color settings so predefined defaults apply when unset.
     *
     * Adds these settings with null values: `branding.text_primary_color` (fallback: black),
     * `branding.text_secondary_color` (fallback: gray-600), `branding.text_inverse_color`
     * (fallback: white), `branding.link_color` (fallback: accent color), and
     * `branding.link_hover_color` (fallback: accent-dark).
     */
    public function up(): void
    {
        // Text colors (nullable, will use defaults if not set)
        $this->migrator->add('branding.text_primary_color', null); // Primary text (default: black)
        $this->migrator->add('branding.text_secondary_color', null); // Secondary text (default: gray-600)
        $this->migrator->add('branding.text_inverse_color', null); // Inverse text (default: white)
        $this->migrator->add('branding.link_color', null); // Link color (default: accent color)
        $this->migrator->add('branding.link_hover_color', null); // Link hover color (default: accent-dark)
    }
};
