<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Completes the card border structural token:
     * card_border_width was added without a color, so a non-zero width
     * rendered an invisible but layout-shifting border. Nullable so existing
     * tenants keep the CSS default in app.css.
     */
    public function up(): void
    {
        $this->migrator->add('branding.card_border_color', null);
    }
};
