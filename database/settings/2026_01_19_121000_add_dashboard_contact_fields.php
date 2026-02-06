<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Adds default dashboard contact settings to the application's settings store.
     *
     * Adds the following keys with their default values:
     * - `dashboard.contact_name`: null (each installation sets its own)
     * - `dashboard.contact_email`: "support@example.org"
     * - `dashboard.contact_url`: "https://www.example.org/kontakt"
     */
    public function up(): void
    {
        $this->migrator->add('dashboard.contact_name', null);
        $this->migrator->add('dashboard.contact_email', 'support@example.org');
        $this->migrator->add('dashboard.contact_url', 'https://www.example.org/kontakt');
    }
};
