<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Adds navigation color settings to the branding settings.
     *
     * Registers three settings: 'branding.nav_text_color' ('#374151'), 
     * 'branding.nav_text_color_inactive' ('#9CA3AF'), and 
     * 'branding.nav_hover_color' ('#FCA5A5').
     */
    public function up(): void
    {
        $this->migrator->add('branding.nav_text_color', '#374151'); // Navigation text color (gray-700)
        $this->migrator->add('branding.nav_text_color_inactive', '#9CA3AF'); // Inactive navigation text color (gray-400, visible on white)
        $this->migrator->add('branding.nav_hover_color', '#FCA5A5'); // Navigation hover color (red-300)
    }

    public function down(): void
    {
        $this->migrator->remove('branding.nav_text_color');
        $this->migrator->remove('branding.nav_text_color_inactive');
        $this->migrator->remove('branding.nav_hover_color');
    }
};

