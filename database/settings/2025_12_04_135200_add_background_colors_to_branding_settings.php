<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    / **
     * Adds default branding background color settings to the settings store.
     *
     * Registers four settings: 'branding.background_color' (nullable default),
     * 'branding.card_background_color' ('#FFFFFF'), 'branding.hero_background_color' ('#111827'),
     * and 'branding.overlay_background_color' ('rgba(0,0,0,0.4)').
     */
    public function up(): void
    {
        // Background colors (nullable, will use defaults if not set)
        $this->migrator->add('branding.background_color', null); // Main background (default: transparent/white)
        $this->migrator->add('branding.card_background_color', '#FFFFFF'); // Card/Content background
        $this->migrator->add('branding.hero_background_color', '#111827'); // Hero block background (gray-900)
        $this->migrator->add('branding.overlay_background_color', 'rgba(0,0,0,0.4)'); // Overlay backdrop
    }
};