<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Updates the default typography_font_weights from [400, 600, 700]
     * to [500, 600, 700] to match the reference site (Medium weight).
     */
    public function up(): void
    {
        $this->migrator->update('branding.typography_font_weights', fn () => [500, 600, 700]);
    }

    public function down(): void
    {
        $this->migrator->update('branding.typography_font_weights', fn () => [400, 600, 700]);
    }
};
