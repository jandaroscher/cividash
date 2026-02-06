<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Apply header-related default settings to the application's settings store.
     *
     * Adds three settings keys used by the header:
     * - `header.navigation_items` initialized to an empty array
     * - `header.show_language_switcher` initialized to `true`
     * - `header.dropdown_enabled` initialized to `false`
     */
    public function up(): void
    {
        $this->migrator->add('header.navigation_items', []);
        $this->migrator->add('header.show_language_switcher', true);
        $this->migrator->add('header.dropdown_enabled', false);
    }
};
