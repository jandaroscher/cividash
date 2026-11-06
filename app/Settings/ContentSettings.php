<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ContentSettings extends Settings
{
    public ?array $hero_content = null;

    public static function group(): string
    {
        return 'hero';
    }

    // Tell Eloquent to cast JSON → array
    protected $casts = [
        'hero_content' => 'array',
    ];
}
