<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Minimal deny-list SVG sanitizer.
 *
 * We deliberately avoid enshrined/svg-sanitize (GPL-2.0-or-later), which is
 * incompatible with this project's EUPL-1.2 license. This sanitizer strips
 * the concrete XSS vectors relevant to SVGs served as <img> from our own
 * origin: <script>, <foreignObject>, on* event handler attributes, and
 * javascript:/data:/external href values.
 *
 * ponytail: deny-list, not a full spec-compliant allow-list sanitizer.
 * Upgrade to a maintained allow-list library if SVG uploads become a wider
 * attack surface (e.g. public self-service uploads) than trusted CMS editors.
 */
class SvgSanitizer
{
    private const DENYLISTED_TAGS = ['script', 'foreignobject', 'iframe', 'embed', 'object'];

    private const HREF_ATTRIBUTES = ['href', 'xlink:href'];

    public static function sanitize(string $svg): string
    {
        // Strip DOCTYPE/ENTITY declarations before parsing to prevent XXE
        // and entity-expansion ("billion laughs") attacks.
        $svg = preg_replace('/<!DOCTYPE[^>]*(\[[^\]]*\])?[^>]*>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/<!ENTITY[^>]*>/is', '', $svg) ?? $svg;

        if (trim($svg) === '') {
            return $svg;
        }

        $previousErrorSetting = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;

            if (! $document->loadXML($svg, LIBXML_NONET | LIBXML_NOCDATA)) {
                // Not parseable as XML — refuse to guess, return an empty SVG
                // rather than risk passing through unsanitized markup.
                return '';
            }

            // Collect first (removeChild while iterating a live NodeList
            // skips siblings), then compare case-insensitively — SVG tag
            // names like foreignObject are case-sensitive in the markup.
            foreach (iterator_to_array($document->getElementsByTagName('*')) as $node) {
                if (in_array(strtolower($node->localName ?? $node->tagName), self::DENYLISTED_TAGS, true)) {
                    $node->parentNode?->removeChild($node);
                }
            }

            self::sanitizeAttributes($document->documentElement);

            return $document->saveXML() ?: '';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }
    }

    public static function sanitizeFile(Filesystem $disk, string $path): void
    {
        $content = $disk->get($path);

        if ($content === null) {
            return;
        }

        $disk->put($path, self::sanitize($content));
    }

    private static function sanitizeAttributes(?DOMElement $element): void
    {
        if ($element === null) {
            return;
        }

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = trim($attribute->nodeValue ?? '');

            if (str_starts_with($name, 'on')) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if (in_array($name, self::HREF_ATTRIBUTES, true) && self::isDisallowedHref($value)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                self::sanitizeAttributes($child);
            }
        }
    }

    private static function isDisallowedHref(string $value): bool
    {
        if ($value === '' || str_starts_with($value, '#')) {
            return false;
        }

        $lower = strtolower($value);

        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:')) {
            return true;
        }

        // Any absolute/external reference (http(s), protocol-relative, or
        // other scheme) is disallowed — icons only reference internal
        // fragments or embedded content.
        return (bool) preg_match('#^([a-z][a-z0-9+.\-]*:|//)#i', $lower);
    }
}
