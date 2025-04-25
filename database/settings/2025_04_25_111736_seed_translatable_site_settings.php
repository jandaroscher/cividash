<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.footer_logos',   []);
        $this->migrator->add('site.social_links',   []);
    }

    public function down(): void
    {
        $this->migrator->remove('site.footer_logos');
        $this->migrator->remove('site.social_links');
    }
};
