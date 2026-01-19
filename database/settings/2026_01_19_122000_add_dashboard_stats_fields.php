<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Adds the dashboard "made with" text setting to the settings store.
     *
     * Registers the 'dashboard.made_with_text' setting without a default text.
     */
    public function up(): void
    {
        $this->migrator->add('dashboard.made_with_text', null);
    }
};