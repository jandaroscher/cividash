<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('branding.primary_color', '#0d47a1');
        $this->migrator->add('branding.secondary_color', '#1976d2');
        $this->migrator->add('branding.logo_url', null);
    }
};
