<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Register the dashboard.show_server_time setting with its default value.
     *
     * Adds the 'dashboard.show_server_time' setting with the boolean value `true`.
     */
    public function up(): void
    {
        $this->migrator->add('dashboard.show_server_time', true);
    }
};
