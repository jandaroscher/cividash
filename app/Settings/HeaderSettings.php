<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class HeaderSettings extends Settings
{
    public array $navigation_items = [];

    public bool $show_language_switcher = true;

    public bool $dropdown_enabled = false;

    /**
     * Get the settings group name for header settings.
     *
     * @return string The settings group identifier "header".
     */
    public static function group(): string
    {
        return 'header';
    }

    protected $casts = [
        'navigation_items' => 'array',
    ];
}
