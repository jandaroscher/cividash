<?php

namespace App\Support;

class LocalAvatar
{
    /**
     * Build a self-contained data: URI SVG avatar with initials, rendered locally
     * so the browser never calls a third-party avatar service (DSB requirement).
     */
    public static function svgDataUri(string $initials, string $backgroundColor): string
    {
        $color = ltrim($backgroundColor, '#');
        if (! preg_match('/^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $color)) {
            $color = '6b7280';
        }
        $initials = htmlspecialchars($initials, ENT_QUOTES | ENT_XML1);

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128">
            <rect width="128" height="128" fill="#{$color}"/>
            <text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#ffffff" font-family="sans-serif" font-size="52">{$initials}</text>
        </svg>
        SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
