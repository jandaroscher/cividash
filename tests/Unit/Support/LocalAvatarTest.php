<?php

namespace Tests\Unit\Support;

use App\Support\LocalAvatar;
use Tests\TestCase;

class LocalAvatarTest extends TestCase
{
    public function test_invalid_color_falls_back_to_default(): void
    {
        $url = LocalAvatar::svgDataUri('AB', '"; alert(1) //');

        $svg = base64_decode(substr($url, strlen('data:image/svg+xml;base64,')));

        $this->assertStringContainsString('fill="#6b7280"', $svg);
        $this->assertStringNotContainsString('alert', $svg);
    }
}
