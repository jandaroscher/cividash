<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Updates the default nav_hover_color from salmon-red (#FCA5A5)
     * to the primary accent color (#e30613).
     */
    public function up(): void
    {
        $this->migrator->update('branding.nav_hover_color', fn () => '#e30613');
    }

    public function down(): void
    {
        $this->migrator->update('branding.nav_hover_color', fn () => '#FCA5A5');
    }
};
