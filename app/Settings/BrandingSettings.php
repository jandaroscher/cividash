<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BrandingSettings extends Settings
{

    public string $primary_color;
    public string $secondary_color;
    public ?string $logo_url;

    public static function group(): string
    {
        return 'branding';
    }
}
