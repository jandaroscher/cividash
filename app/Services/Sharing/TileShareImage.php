<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Services\Sharing;

use App\Models\MetricValue;
use App\Models\Tile;
use App\Settings\BrandingSettings;
use App\Settings\GeneralSettings;
use GdImage;
use Illuminate\Support\Facades\Storage;
use NumberFormatter;

/**
 * Open Graph preview image of a single tile (title, headline value, branding),
 * rendered with GD so no external service or browser is needed.
 */
class TileShareImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    // Bump when the layout changes so cached images and crawler caches refresh.
    private const LAYOUT_VERSION = 1;

    private const FALLBACK_COLOR = '#0d47a1';

    public function __construct(
        protected BrandingSettings $branding,
        protected GeneralSettings $general,
    ) {}

    /**
     * Everything the image shows. The version hash is derived from it, so any
     * data or branding change yields a new image URL.
     *
     * @return array<string, mixed>
     */
    public function content(Tile $tile, string $locale): array
    {
        $title = $tile->getTranslation('title', $locale, false)
            ?: $tile->getTranslation('title', 'de', false)
            ?: '';

        return [
            'layout' => self::LAYOUT_VERSION,
            'locale' => $locale,
            'title' => (string) $title,
            'description' => $this->plainText((string) ($tile->getTranslation('description', $locale, false)
                ?: $tile->getTranslation('description', 'de', false)
                ?: '')),
            'headline' => $this->headline($tile, $locale),
            'site_name' => $this->siteName(),
            'logo' => rescue(fn () => $this->branding->logo_url, null, false),
            'color' => $this->color(rescue(fn () => $this->branding->primary_color, null, false)),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public function version(array $content): string
    {
        return substr(hash('sha256', json_encode($content)), 0, 16);
    }

    public function path(Tile $tile, string $locale, string $version): string
    {
        return "/share/tiles/{$tile->id}.png?locale={$locale}&v={$version}";
    }

    public function alt(array $content): string
    {
        $headline = $content['headline'];

        return $headline
            ? trim("{$content['title']}: {$headline['value']} {$headline['unit']}")
            : $content['title'];
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public function render(array $content): string
    {
        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        $white = imagecolorallocate($img, 255, 255, 255);
        $ink = imagecolorallocate($img, 25, 25, 25);
        $muted = imagecolorallocate($img, 90, 90, 90);
        $accent = $this->allocate($img, $content['color']);
        imagefilledrectangle($img, 0, 0, self::WIDTH, self::HEIGHT, $white);
        imagefilledrectangle($img, 0, 0, 23, self::HEIGHT, $accent);

        $x = 80;
        $maxWidth = self::WIDTH - $x - 80;

        if (! $this->drawLogo($img, $content['logo'], $x, 56)) {
            $this->text($img, 30, $x, 90, $accent, $content['site_name'], bold: true, maxWidth: $maxWidth);
        }

        $y = 200;
        foreach ($this->wrap($content['title'], 52, $maxWidth, $content['headline'] ? 2 : 3, bold: true) as $line) {
            $this->text($img, 52, $x, $y, $ink, $line, bold: true);
            $y += 70;
        }

        if ($headline = $content['headline']) {
            $y += 50;
            $valueWidth = $this->text($img, 88, $x, $y + 40, $accent, $headline['value'], bold: true);
            if ($headline['unit'] !== '') {
                $this->text($img, 36, $x + $valueWidth + 16, $y + 40, $accent, $headline['unit'], bold: true,
                    maxWidth: $maxWidth - $valueWidth - 16);
            }
            $caption = implode(' · ', array_filter([$headline['label'], $headline['period']]));
            $this->text($img, 28, $x, $y + 100, $muted, $caption, maxWidth: $maxWidth);
        } else {
            $y += 20;
            foreach ($this->wrap($content['description'], 30, $maxWidth, 3) as $line) {
                $this->text($img, 30, $x, $y, $muted, $line);
                $y += 46;
            }
        }

        ob_start();
        imagepng($img, null, 9);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    /**
     * First active metric of the tile with its value in the most recent period,
     * which is what the tile card shows by default.
     *
     * @return array{value: string, unit: string, label: string, period: string}|null
     */
    protected function headline(Tile $tile, string $locale): ?array
    {
        $definition = $tile->metricDefinitions()->where('is_active', true)->first();
        if (! $definition) {
            return null;
        }

        $value = MetricValue::query()
            ->where('metric_values.metric_definition_id', $definition->id)
            ->where('metric_values.is_active', true)
            ->whereNotNull('metric_values.value')
            ->join('time_periods', 'time_periods.id', '=', 'metric_values.time_period_id')
            ->orderByDesc('time_periods.period_key')
            ->select('metric_values.value', 'time_periods.period_key', 'time_periods.label as period_label')
            ->first();

        if (! $value) {
            return null;
        }

        $formatter = new NumberFormatter($locale === 'en' ? 'en_US' : 'de_DE', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);

        return [
            'value' => (string) $formatter->format((float) $value->value),
            'unit' => (string) ($definition->getTranslation('unit', $locale, false) ?: $definition->getTranslation('unit', 'de', false) ?: ''),
            'label' => (string) ($definition->getTranslation('label', $locale, false) ?: $definition->getTranslation('label', 'de', false) ?: ''),
            'period' => (string) ($value->period_label ?: $value->period_key),
        ];
    }

    protected function siteName(): string
    {
        $name = rescue(fn () => $this->general->site_name, null, false);

        return $name ?: (string) config('app.name', 'Dashboard');
    }

    protected function color(?string $value): string
    {
        return is_string($value) && preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value)
            ? $value
            : self::FALLBACK_COLOR;
    }

    protected function allocate(GdImage $img, string $hex): int
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        [$r, $g, $b] = array_map('hexdec', str_split($hex, 2));

        return imagecolorallocate($img, $r, $g, $b);
    }

    /**
     * Draws a raster logo (PNG, JPEG, GIF, WebP) scaled to 72px height. GD cannot
     * read SVG, so callers fall back to the site name in that case.
     */
    protected function drawLogo(GdImage $img, ?string $path, int $x, int $y): bool
    {
        if (! $path || str_ends_with(strtolower($path), '.svg')) {
            return false;
        }

        $bytes = rescue(fn () => Storage::disk('public')->get($path), null, false);
        $logo = $bytes ? @imagecreatefromstring($bytes) : false;
        if (! $logo) {
            return false;
        }

        $height = 72;
        $width = (int) min(480, round(imagesx($logo) * $height / max(1, imagesy($logo))));
        imagecopyresampled($img, $logo, $x, $y, 0, 0, $width, $height, imagesx($logo), imagesy($logo));
        imagedestroy($logo);

        return true;
    }

    /**
     * Draws one line (cut with an ellipsis when maxWidth is given) and returns its width.
     */
    protected function text(GdImage $img, int $size, int $x, int $y, int $color, string $text, bool $bold = false, ?int $maxWidth = null): int
    {
        if ($maxWidth !== null) {
            $text = $this->fit($text, $size, $maxWidth, $bold);
        }
        $box = imagettftext($img, $size, 0, $x, $y, $color, $this->font($bold), $text);

        return $box ? $box[2] - $box[0] : 0;
    }

    /**
     * @return list<string>
     */
    protected function wrap(string $text, int $size, int $maxWidth, int $maxLines, bool $bold = false): array
    {
        $lines = [];
        $current = '';
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";
            if ($current === '' || $this->width($candidate, $size, $bold) <= $maxWidth) {
                $current = $candidate;

                continue;
            }
            if (count($lines) === $maxLines - 1) {
                $lines[] = $this->fit($current, $size, $maxWidth, $bold, forceEllipsis: true);

                return $lines;
            }
            $lines[] = $current;
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $this->fit($current, $size, $maxWidth, $bold);
        }

        return $lines;
    }

    protected function fit(string $text, int $size, int $maxWidth, bool $bold, bool $forceEllipsis = false): string
    {
        if (! $forceEllipsis && $this->width($text, $size, $bold) <= $maxWidth) {
            return $text;
        }

        while ($text !== '' && $this->width($text.'…', $size, $bold) > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text, ' .,;:').'…';
    }

    protected function width(string $text, int $size, bool $bold): int
    {
        $box = imagettfbbox($size, 0, $this->font($bold), $text);

        return $box ? $box[2] - $box[0] : 0;
    }

    protected function font(bool $bold): string
    {
        return resource_path($bold ? 'fonts/OpenSans-Bold.ttf' : 'fonts/OpenSans-Regular.ttf');
    }

    protected function plainText(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }
}
