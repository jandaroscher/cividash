<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Add default typography font size settings to the branding configuration.
     *
     * Inserts the `branding.typography_font_sizes` settings key with rem-based defaults for
     * base, small, large, and heading sizes (h1–h6). Each value is optional and will fall
     * back to CSS defaults if not set.
     */
    public function up(): void
    {
        // Typography font sizes (nullable, will use CSS defaults if not set)
        $this->migrator->add('branding.typography_font_sizes', [
            'base' => '1rem',
            'small' => '0.875rem',
            'large' => '1.125rem',
            'h1' => '3rem',
            'h2' => '2.25rem',
            'h3' => '1.875rem',
            'h4' => '1.5rem',
            'h5' => '1.25rem',
            'h6' => '1.125rem',
        ]);
    }
};
