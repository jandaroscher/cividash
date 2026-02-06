<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Registers dashboard settings for documentation and user manual URLs.
     *
     * Adds the following settings keys with their default values:
     * - `dashboard.open_source_docs_url`: `https://www.example.org/kontakt`
     * - `dashboard.user_manual_url`: `https://www.example.org/kontakt`
     */
    public function up(): void
    {
        $this->migrator->add('dashboard.open_source_docs_url', 'https://www.example.org/kontakt');
        $this->migrator->add('dashboard.user_manual_url', 'https://www.example.org/kontakt');
    }
};
