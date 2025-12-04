<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Adds branding-related settings with their default values to the application's settings store.
     *
     * Initializes the following settings:
     * - `branding.accent_color`: null (falls back to `primary_color` when unset)
     * - `branding.typography_font_family`: `'Open Sans'`
     * - `branding.typography_font_weights`: `[400, 600, 700]`
     * - `branding.slider_colors`: `['rail' => '#191919', 'handle' => '#E30613', 'handleBorder' => '#191919']`
     */
    public function up(): void
    {
        // Accent color (nullable, falls nicht gesetzt wird primary_color verwendet)
        $this->migrator->add('branding.accent_color', null);
        
        // Typography settings
        $this->migrator->add('branding.typography_font_family', 'Open Sans');
        $this->migrator->add('branding.typography_font_weights', [400, 600, 700]);
        
        // Slider colors
        $this->migrator->add('branding.slider_colors', [
            'rail' => '#191919',
            'handle' => '#E30613',
            'handleBorder' => '#191919',
        ]);
    }
};
