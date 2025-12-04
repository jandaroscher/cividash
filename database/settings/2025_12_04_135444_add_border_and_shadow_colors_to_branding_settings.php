<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Registers branding color settings.
     *
     * Adds three settings used for UI theming:
     * - `branding.border_color`: nullable (will use gray-300 when unset)
     * - `branding.divider_color`: nullable (will use gray-200 when unset)
     * - `branding.shadow_color`: defaults to `#000000` (black)
     */
    public function up(): void
    {
        // Border and shadow colors (nullable, will use defaults if not set)
        $this->migrator->add('branding.border_color', null); // Standard border color (default: gray-300)
        $this->migrator->add('branding.divider_color', null); // Divider color (default: gray-200)
        $this->migrator->add('branding.shadow_color', '#000000'); // Shadow color (default: black)
    }
};