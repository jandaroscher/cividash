<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('footer.footer_links', []);
        $this->migrator->add('footer.footer_logos', []);
        $this->migrator->add('footer.social_links', []);
    }
};
