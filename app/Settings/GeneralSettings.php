<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;

    public bool $site_active = true;

    public ?string $favicon = null;

    /**
     * Get the settings group name for this settings class.
     *
     * @return string The group name used to store and retrieve these settings ('general').
     */
    public static function group(): string
    {
        return 'general';
    }
}
