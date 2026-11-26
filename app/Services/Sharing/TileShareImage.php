<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Services\Sharing;

use App\Models\MetricValue;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Settings\BrandingSettings;
use App\Settings\GeneralSettings;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Open Graph preview image of a single tile (title, metric values, branding),
 * rendered with GD so no external service or browser is needed.
 */
class TileShareImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    // Bump when the layout changes so cached images and crawler caches refresh.
    private const LAYOUT_VERSION = 3;

    private const FALLBACK_COLOR = '#0d47a1';

    private const BRANDING_COLORS = [
        'primary_color', 'secondary_color', 'accent_color', 'card_background_color', 'background_color',
        'text_primary_color', 'text_secondary_color', 'text_inverse_color',
    ];

    // Layout in Figma pixels: content inset, panel inset, inner panel width, further-metric column.
    private const INSET = 64;

    private const PANEL_INSET = 30;

    private const PANEL_WIDTH = 1012;

    private const COLUMN = 316;

    private const COLUMN_GAP = 32;

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
            ?: $tile->getTranslation('title', 'en', false)
            ?: '';

        return [
            'layout' => self::LAYOUT_VERSION,
            'locale' => $locale,
            'title' => (string) $title,
            'description' => $this->plainText((string) ($tile->getTranslation('description', $locale, false)
                ?: $tile->getTranslation('description', 'de', false)
                ?: '')),
            ...$this->metrics($tile, $locale),
            'site_name' => $this->siteName(),
            'logo' => $logo = rescue(fn () => $this->branding->logo_url, null, false),
            // Logo uploads keep their file name, so a replaced logo only shows in its timestamp.
            'logo_modified' => $logo ? rescue(fn () => Storage::disk('public')->lastModified($logo), null, false) : null,
            'colors' => $this->colors(collect(self::BRANDING_COLORS)
                ->mapWithKeys(fn ($key) => [$key => rescue(fn () => $this->branding->{$key}, null, false)])
                ->all()),
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
        if (! $content['headline']) {
            return $content['title'];
        }
        $values = array_map(fn ($m) => trim("{$m['value']} {$m['unit']}"), [$content['headline'], ...$content['more'] ?? []]);

        return "{$content['title']}: ".implode('; ', $values);
    }

    /**
     * Colour roles of the image, resolved from the branding settings. Unset text
     * colours use the frontend defaults; a text colour below 4.5:1 against its
     * background falls back as the design specifies.
     *
     * @param  array<string, mixed>  $branding
     * @return array{surface: string, accent: string, panel: string, panel_text: string, title: string, sender: string}
     */
    public function colors(array $branding): array
    {
        $hex = fn (string $key) => $this->hex($branding[$key] ?? null);

        $surface = $hex('card_background_color') ?? $hex('background_color') ?? '#ffffff';
        $panel = $hex('primary_color') ?? self::FALLBACK_COLOR;
        $textPrimary = $hex('text_primary_color') ?? '#000000';
        $readable = fn (string $text, string $background) => $this->contrast($text, $background) >= 4.5;

        $panelText = collect([$hex('text_inverse_color') ?? '#ffffff', $textPrimary])
            ->first(fn ($color) => $readable($color, $panel)) ?? $this->blackOrWhite($panel);
        $title = $readable($textPrimary, $surface) ? $textPrimary : $this->blackOrWhite($surface);
        $sender = $hex('text_secondary_color') ?? '#4b5563';

        return [
            'surface' => $surface,
            'accent' => $hex('accent_color') ?? $hex('secondary_color') ?? $panel,
            'panel' => $panel,
            'panel_text' => $panelText,
            'title' => $title,
            'sender' => $readable($sender, $surface) ? $sender : $title,
        ];
    }

    /**
     * Draws the layout from the design: accent bar, a head with sender and title,
     * and a panel in the primary colour with the values or the description.
     *
     * @param  array<string, mixed>  $content
     */
    public function render(array $content): string
    {
        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        $colors = array_map(fn ($hex) => $this->allocate($img, $hex), $content['colors']);
        imagefilledrectangle($img, 0, 0, self::WIDTH - 1, self::HEIGHT - 1, $colors['surface']);
        imagefilledrectangle($img, 0, 0, self::WIDTH - 1, 19, $colors['accent']);

        $headline = $content['headline'];
        $more = $headline ? $this->fittingMore($content['more'] ?? []) : [];
        $panelHeight = $more ? 308 : 240;
        $panelTop = self::HEIGHT - self::PANEL_INSET - $panelHeight;

        $this->drawHead($img, $content, $colors, 20, $panelTop);

        imagefilledrectangle($img, self::PANEL_INSET, $panelTop, self::WIDTH - self::PANEL_INSET - 1, $panelTop + $panelHeight - 1, $colors['panel']);
        $x = self::PANEL_INSET + self::INSET;
        $innerTop = $panelTop + 28;
        $innerHeight = $panelHeight - 58;
        $ink = $colors['panel_text'];

        if ($headline) {
            $y = $innerTop + intdiv($innerHeight - 140 - ($more ? 110 : 0), 2);
            $this->drawHeadline($img, $headline, $x, $y, $ink);
            $caption = implode(' · ', array_filter([$headline['label'], $headline['period']]));
            $this->line($img, 28, 36, $x, $y + 104, $ink, $this->fitWords($caption, 28, self::PANEL_WIDTH, false));

            foreach ($more as $i => $metric) {
                $cx = $x + $i * (self::COLUMN + self::COLUMN_GAP);
                $valueWidth = $this->line($img, 40, 48, $cx, $y + 170, $ink, $metric['value'], true);
                if ($metric['unit'] !== '') {
                    $this->onBaseline($img, 24, $cx + $valueWidth + 8, $this->baseline($y + 170, 40, 48), $ink, $metric['unit'], true);
                }
                $this->line($img, 24, 32, $cx, $y + 218, $ink, $this->fitWords($metric['label'], 24, self::COLUMN, false));
            }
        } else {
            $lines = $this->wrap($content['description'], 32, self::PANEL_WIDTH, 3, false);
            $y = $innerTop + intdiv($innerHeight - count($lines) * 44, 2);
            foreach ($lines as $i => $text) {
                $this->line($img, 32, 44, $x, $y + $i * 44, $ink, $text);
            }
        }

        ob_start();
        imagepng($img, null, 9);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, int>  $colors
     */
    protected function drawHead(GdImage $img, array $content, array $colors, int $top, int $bottom): void
    {
        $width = self::WIDTH - 2 * self::INSET;
        $logo = $this->loadLogo($content['logo']);
        $lines = $this->wrap($content['title'], 52, $width, 2, true);
        $senderHeight = $logo ? 80 : 32;
        $y = $top + max(24, intdiv($bottom - $top - ($senderHeight + 20 + count($lines) * 60), 2));

        if ($logo) {
            $scale = min(80 / max(1, imagesy($logo)), 360 / max(1, imagesx($logo)));
            $w = max(1, (int) round(imagesx($logo) * $scale));
            $h = max(1, (int) round(imagesy($logo) * $scale));
            imagecopyresampled($img, $logo, self::INSET, $y + intdiv(80 - $h, 2), 0, 0, $w, $h, imagesx($logo), imagesy($logo));
            imagedestroy($logo);
        } else {
            $this->line($img, 26, 32, self::INSET, $y, $colors['sender'], $this->fitWords($content['site_name'], 26, $width, true), true);
        }

        $y += $senderHeight + 20;
        foreach ($lines as $i => $text) {
            $this->line($img, 52, 60, self::INSET, $y + $i * 60, $colors['title'], $text, true);
        }
    }

    /**
     * Headline value at 86px, one step smaller (72, then 64) while value and unit
     * are wider than the panel; never larger than 86.
     *
     * @param  array{value: string, unit: string}  $headline
     */
    protected function drawHeadline(GdImage $img, array $headline, int $x, int $y, int $ink): void
    {
        $unitWidth = $headline['unit'] === '' ? 0 : 16 + $this->width($headline['unit'], 40, true);
        foreach ([86, 72, 64] as $size) {
            if ($this->width($headline['value'], $size, true) + $unitWidth <= self::PANEL_WIDTH) {
                break;
            }
        }
        // The number is never cut while it fits on its own; the unit gives way at a word end.
        $value = $this->fit($headline['value'], $size, self::PANEL_WIDTH, true);
        $valueWidth = $this->line($img, $size, 104, $x, $y, $ink, $value, true);
        if ($headline['unit'] !== '' && self::PANEL_WIDTH - $valueWidth - 16 > 0) {
            $this->onBaseline($img, 40, $x + $valueWidth + 16, $this->baseline($y, $size, 104), $ink,
                $this->fitWords($headline['unit'], 40, self::PANEL_WIDTH - $valueWidth - 16, true), true);
        }
    }

    /**
     * Further metrics are shown only when every one fits its column; otherwise
     * the row is left out and the panel stays at its smaller height.
     *
     * @param  list<array{value: string, unit: string, label: string}>  $more
     * @return list<array{value: string, unit: string, label: string}>
     */
    protected function fittingMore(array $more): array
    {
        foreach ($more as $metric) {
            $width = $this->width($metric['value'], 40, true)
                + ($metric['unit'] === '' ? 0 : 8 + $this->width($metric['unit'], 24, true));
            if ($width > self::COLUMN) {
                return [];
            }
        }

        return $more;
    }

    /**
     * Values in the newest period of any active metric, which is the period the
     * tile card opens on: the first metric as headline, up to three more below.
     *
     * @return array{headline: array{value: string, unit: string, label: string, period: string}|null, more: list<array{value: string, unit: string, label: string}>}
     */
    protected function metrics(Tile $tile, string $locale): array
    {
        $none = ['headline' => null, 'more' => []];
        $definitions = $tile->metricDefinitions()->where('is_active', true)->get();
        if ($definitions->isEmpty()) {
            return $none;
        }

        $period = TimePeriod::query()
            ->whereIn('id', MetricValue::query()
                ->whereIn('metric_definition_id', $definitions->pluck('id'))
                ->where('is_active', true)
                ->select('time_period_id'))
            ->orderByDesc('period_key')
            ->first();
        if (! $period) {
            return $none;
        }

        $values = MetricValue::query()
            ->whereIn('metric_definition_id', $definitions->pluck('id'))
            ->where('time_period_id', $period->id)
            ->where('is_active', true)
            ->whereNotNull('value')
            ->pluck('value', 'metric_definition_id');

        // Without the first metric's value there is no headline, so no stale figure either.
        if (! $values->has($definitions->first()->id)) {
            return $none;
        }

        $metrics = $definitions->filter(fn ($d) => $values->has($d->id))->take(4)->map(fn ($d) => [
            'value' => $this->formatNumber((float) $values[$d->id], $locale),
            'unit' => (string) ($d->getTranslation('unit', $locale, false) ?: $d->getTranslation('unit', 'de', false) ?: ''),
            'label' => (string) ($d->getTranslation('label', $locale, false) ?: $d->getTranslation('label', 'de', false) ?: ''),
        ])->values()->all();

        return [
            'headline' => $metrics[0] + ['period' => (string) ($period->label ?: $period->period_key)],
            'more' => array_slice($metrics, 1),
        ];
    }

    /**
     * Up to two decimals without trailing zeros. Separators are set explicitly
     * because the Alpine runtime image ships ICU data for English locales only.
     */
    protected function formatNumber(float $value, string $locale): string
    {
        $decimals = strlen(rtrim(explode('.', number_format(abs($value), 2, '.', ''))[1], '0'));

        return $locale === 'en'
            ? number_format($value, $decimals, '.', ',')
            : number_format($value, $decimals, ',', '.');
    }

    protected function siteName(): string
    {
        $name = rescue(fn () => $this->general->site_name, null, false);

        return $name ?: (string) config('app.name', 'Dashboard');
    }

    /** Only #rgb/#rrggbb; rgb()/rgba() set via the API fall back to the next colour role, since alpha has no meaning on the image. */
    protected function hex(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', trim($value))) {
            return null;
        }
        $hex = strtolower(ltrim(trim($value), '#'));

        return '#'.(strlen($hex) === 3 ? $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2] : $hex);
    }

    /**
     * WCAG 2 contrast ratio of two #rrggbb colours.
     */
    protected function contrast(string $a, string $b): float
    {
        $luminance = function (string $hex): float {
            $channels = array_map(function ($c) {
                $c = hexdec($c) / 255;

                return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            }, str_split(ltrim($hex, '#'), 2));

            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };
        [$light, $dark] = [max($luminance($a), $luminance($b)), min($luminance($a), $luminance($b))];

        return ($light + 0.05) / ($dark + 0.05);
    }

    protected function blackOrWhite(string $background): string
    {
        return $this->contrast('#000000', $background) >= $this->contrast('#ffffff', $background) ? '#000000' : '#ffffff';
    }

    protected function allocate(GdImage $img, string $hex): int
    {
        [$r, $g, $b] = array_map('hexdec', str_split(ltrim($hex, '#'), 2));

        return imagecolorallocate($img, $r, $g, $b);
    }

    /**
     * Raster logo (PNG, JPEG, GIF). GD cannot read SVG, so the caller falls
     * back to the site name then.
     */
    protected function loadLogo(?string $path): ?GdImage
    {
        if (! $path || str_ends_with(strtolower($path), '.svg')) {
            return null;
        }
        $bytes = rescue(fn () => Storage::disk('public')->get($path), null, false);

        return ($bytes ? @imagecreatefromstring($bytes) : false) ?: null;
    }

    /**
     * Baseline of a single line of text set in a line box, as Figma places it:
     * half the leading above, using Open Sans' ascender (1.069 em) and descender (0.293 em).
     */
    protected function baseline(int $top, int $px, int $lineHeight): int
    {
        return (int) round($top + $lineHeight / 2 + (1.069 - 0.293) / 2 * $px);
    }

    /**
     * Draws one line in a line box whose top is $top and returns its width.
     */
    protected function line(GdImage $img, int $px, int $lineHeight, int $x, int $top, int $color, string $text, bool $bold = false): int
    {
        return $this->onBaseline($img, $px, $x, $this->baseline($top, $px, $lineHeight), $color, $text, $bold);
    }

    protected function onBaseline(GdImage $img, int $px, int $x, int $baseline, int $color, string $text, bool $bold = false): int
    {
        $box = imagettftext($img, $this->pt($px), 0, $x, $baseline, $color, $this->font($bold), $text);

        return $box ? $box[2] - $box[0] : 0;
    }

    /**
     * Greedy word wrap; the last allowed line takes the rest and is cut at a word end.
     *
     * @return list<string>
     */
    protected function wrap(string $text, int $px, int $maxWidth, int $maxLines, bool $bold): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $lines = [];
        $current = '';

        while ($words) {
            if (count($lines) === $maxLines - 1) {
                $lines[] = $this->fitWords(implode(' ', $words), $px, $maxWidth, $bold);

                return $lines;
            }
            $word = array_shift($words);
            $candidate = $current === '' ? $word : "{$current} {$word}";
            if ($current === '' || $this->width($candidate, $px, $bold) <= $maxWidth) {
                $current = $candidate;

                continue;
            }
            $lines[] = $this->fitWords($current, $px, $maxWidth, $bold);
            $current = '';
            array_unshift($words, $word);
        }
        if ($current !== '') {
            $lines[] = $this->fitWords($current, $px, $maxWidth, $bold);
        }

        return $lines;
    }

    /**
     * Cuts a line at the last word end that still fits with an ellipsis; a
     * single word that is too long is cut inside the word.
     */
    protected function fitWords(string $text, int $px, int $maxWidth, bool $bold): string
    {
        if ($this->width($text, $px, $bold) <= $maxWidth) {
            return $text;
        }
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $prefix = '';
        $best = null;
        foreach (array_slice($words, 0, -1) as $word) {
            $prefix = ltrim($prefix.' '.$word);
            $candidate = preg_replace('/[\s.,;:·–-]+$/u', '', $prefix).'…';
            if ($this->width($candidate, $px, $bold) > $maxWidth) {
                break;
            }
            $best = $candidate;
        }

        return $best ?? $this->fit($words[0] ?? '', $px, $maxWidth, $bold, count($words) > 1);
    }

    /** $cut forces the ellipsis, for text that already lost words after it. */
    protected function fit(string $text, int $px, int $maxWidth, bool $bold, bool $cut = false): string
    {
        if (! $cut && $this->width($text, $px, $bold) <= $maxWidth) {
            return $text;
        }
        if ($this->width('…', $px, $bold) > $maxWidth) {
            return '';
        }
        while ($text !== '' && $this->width($text.'…', $px, $bold) > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text, ' .,;:').'…';
    }

    protected function width(string $text, int $px, bool $bold): int
    {
        $box = imagettfbbox($this->pt($px), 0, $this->font($bold), $text);

        return $box ? $box[2] - $box[0] : 0;
    }

    // The design is in pixels; GD's FreeType renders at 96 dpi, so 1px = 0.75pt.
    protected function pt(int $px): float
    {
        return $px * 0.75;
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
