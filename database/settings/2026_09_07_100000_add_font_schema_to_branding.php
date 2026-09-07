<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Extended font schema: separate
     * heading/body font stacks, a base-size scale, and self-hosted font
     * faces. All nullable/default "default" so existing tenants keep
     * today's rendering (single --font-family, --font-size-base: 1rem)
     * without a data backfill.
     */
    public function up(): void
    {
        $this->migrator->add('branding.font_family_heading', null);
        $this->migrator->add('branding.font_family_body', null);
        $this->migrator->add('branding.font_scale', 'default');
        $this->migrator->add('branding.font_faces', []);
    }
};
