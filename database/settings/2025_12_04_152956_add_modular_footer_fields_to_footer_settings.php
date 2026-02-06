<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Registers default modular footer settings used by the application.
     *
     * Adds default values for footer layout, column count, social links toggle, and copyright text.
     */
    public function up(): void
    {
        $this->migrator->add('footer.layout_type', 'single-row');
        $this->migrator->add('footer.columns', 3);
        $this->migrator->add('footer.social_links_enabled', true);
        $this->migrator->add('footer.copyright_text', null);
    }
};
