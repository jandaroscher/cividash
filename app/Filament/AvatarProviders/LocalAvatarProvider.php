<?php

namespace App\Filament\AvatarProviders;

use App\Support\LocalAvatar;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Replaces Filament's default UiAvatarsProvider (which calls an external avatar
 * third party) with a locally rendered SVG data: URI (DSB requirement).
 */
class LocalAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = trim((string) Filament::getNameForDefaultAvatar($record));
        $words = preg_split('/\s+/', $name);
        $initials = count($words) >= 2
            ? mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1))
            : mb_strtoupper(mb_substr($name, 0, 2));

        return LocalAvatar::svgDataUri($initials ?: '?', '6b7280');
    }
}
