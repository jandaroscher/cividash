<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class FooterSettings extends Settings
{
    public array $footer_links = [];

    public array $footer_logos = [];

    public array $social_links = [];

    public static function group(): string
    {
        return 'footer';
    }
}
