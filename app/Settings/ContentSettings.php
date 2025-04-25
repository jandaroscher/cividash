<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ContentSettings extends Settings
{
    public array|null $hero_content = null;

    public static function group(): string
    {
        return 'hero';
    }

    // Tell Eloquent to cast JSON → array
    protected $casts = [
        'hero_content' => 'array',
    ];
}
