<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('branding.tile_color_source_group_id', null);
        $this->migrator->add('branding.tile_background_category_group_id', null);
    }
};
