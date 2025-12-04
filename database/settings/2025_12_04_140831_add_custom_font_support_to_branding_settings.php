<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Adds nullable settings keys for custom branding fonts to the settings store.
     *
     * Registers the `branding.typography_custom_font_name` and
     * `branding.typography_custom_font_file` settings with `null` values.
     */
    public function up(): void
    {
        // Custom font support (nullable)
        $this->migrator->add('branding.typography_custom_font_name', null);
        $this->migrator->add('branding.typography_custom_font_file', null);
    }
};