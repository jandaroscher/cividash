<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Delete the 'footer.footer_logos' settings entry from configuration storage.
     */
    public function up(): void
    {
        $this->migrator->delete('footer.footer_logos');
    }
};