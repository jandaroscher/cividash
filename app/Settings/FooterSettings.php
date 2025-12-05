<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class FooterSettings extends Settings
{
    public array $footer_navigation_items = [];

    public array $social_links = [];

    public string $layout_type = 'single-row';

    public int $columns = 3;

    public bool $social_links_enabled = true;

    public ?string $copyright_text = null;

    /**
     * Get the settings group name for footer settings.
     *
     * @return string The settings group identifier: 'footer'.
     */
    public static function group(): string
    {
        return 'footer';
    }
}