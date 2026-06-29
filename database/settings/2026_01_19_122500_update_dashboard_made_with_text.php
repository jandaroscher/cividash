<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Intentionally empty. This migration once rewrote a broken default text;
     * made_with_text no longer has a default, and installs that ran the old
     * version keep their stored value.
     */
    public function up(): void {}
};
