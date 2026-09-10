<?php

namespace Tests\Unit;

use App\Support\SvgSanitizer;
use PHPUnit\Framework\TestCase;

class SvgSanitizerTest extends TestCase
{
    public function test_strips_script_tag(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle r="1"/></svg>';

        $result = SvgSanitizer::sanitize($svg);

        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('<circle', $result);
    }

    public function test_strips_foreign_object(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></body></foreignObject></svg>';

        $result = SvgSanitizer::sanitize($svg);

        $this->assertStringNotContainsString('foreignObject', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function test_strips_onload_attribute(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><circle r="1"/></svg>';

        $result = SvgSanitizer::sanitize($svg);

        $this->assertStringNotContainsString('onload', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function test_strips_javascript_href(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href="javascript:alert(1)"><circle r="1"/></a></svg>';

        $result = SvgSanitizer::sanitize($svg);

        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function test_strips_data_href(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><a href="data:text/html,<script>alert(1)</script>"><circle r="1"/></a></svg>';

        $result = SvgSanitizer::sanitize($svg);

        $this->assertStringNotContainsString('data:text/html', $result);
    }

    public function test_strips_external_xlink_href(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="https://evil.example/x.svg#icon"/></svg>';

        $result = SvgSanitizer::sanitize($svg);

        $this->assertStringNotContainsString('evil.example', $result);
    }

    public function test_keeps_internal_fragment_href(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><defs><circle id="icon" r="1"/></defs><use xlink:href="#icon"/></svg>';

        $result = SvgSanitizer::sanitize($svg);

        $this->assertStringContainsString('#icon', $result);
    }

    public function test_strips_doctype_and_nested_entities(): void
    {
        $svg = <<<'SVG'
<?xml version="1.0"?>
<!DOCTYPE svg [
  <!ENTITY a "lol">
  <!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;&a;&a;">
]>
<svg xmlns="http://www.w3.org/2000/svg"><title>&b;</title></svg>
SVG;

        $result = SvgSanitizer::sanitize($svg);

        $this->assertStringNotContainsString('<!DOCTYPE', $result);
        $this->assertStringNotContainsString('<!ENTITY', $result);
        $this->assertStringNotContainsString('lol', $result);
    }

    public function test_clean_svg_stays_semantically_equivalent(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="10"/></svg>';

        $result = SvgSanitizer::sanitize($svg);

        $this->assertStringContainsString('viewBox="0 0 24 24"', $result);
        $this->assertStringContainsString('<path', $result);
        $this->assertStringContainsString('<circle', $result);
        $this->assertStringContainsString('cx="12"', $result);
    }
}
