<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Adds header and footer background color settings to the branding settings.
     *
     * Registers two settings: 'branding.header_background_color' ('#FFFFFF') and
     * 'branding.footer_background_color' ('#E5E7EB').
     */
    public function up(): void
    {
        $this->migrator->add('branding.header_background_color', '#FFFFFF'); // Header background (white)
        $this->migrator->add('branding.footer_background_color', '#E5E7EB'); // Footer background (gray-200)
    }
};
