<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Services\Export;

use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tile;
use Generator;

/**
 * Builds the row stream for a Tile-based export.
 *
 * Emits one row per (tile, metric_definition, metric_value) tuple.
 * Tiles without any active metrics still produce a single row with
 * null metric/value fields so that tile metadata is preserved in the
 * export.
 *
 * BelongsToTenant global scope ensures automatic tenant isolation —
 * no explicit tenant filter needed here.
 */
class TileExportCollector
{
    /**
     * Stream rows for the given query as a generator.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function collect(ExportQuery $query): Generator
    {
        $tilesQuery = Tile::query()
            ->where('is_public', true)
            ->with([
                'categories' => function ($q) {
                    $q->where('is_active', true)
                        ->whereHas('group', fn ($g) => $g->where('is_active', true))
                        ->with('group');
                },
                'metricDefinitions' => function ($q) {
                    $q->where('is_active', true)
                        ->with([
                            'metricValues' => function ($vq) {
                                $vq->where('is_active', true)->with('timePeriod');
                            },
                        ]);
                },
            ])
            ->orderBy('position');

        if (! empty($query->tileIds)) {
            $tilesQuery->whereIn('id', $query->tileIds);
        }

        if (! empty($query->categoryKeys)) {
            $tilesQuery->whereHas('categories', function ($q) use ($query) {
                $q->whereIn('key', $query->categoryKeys);
            });
        }

        foreach ($tilesQuery->lazy(200) as $tile) {
            /** @var Tile $tile */
            $metricDefinitions = $tile->metricDefinitions;

            if ($metricDefinitions->isEmpty()) {
                yield $this->projectRow($tile, null, null, $query);

                continue;
            }

            $emittedForTile = false;

            foreach ($metricDefinitions as $md) {
                /** @var MetricDefinition $md */
                $values = $this->filterValuesByYear($md->metricValues, $query);

                if ($values->isEmpty()) {
                    yield $this->projectRow($tile, $md, null, $query);
                    $emittedForTile = true;

                    continue;
                }

                foreach ($values as $mv) {
                    /** @var MetricValue $mv */
                    yield $this->projectRow($tile, $md, $mv, $query);
                    $emittedForTile = true;
                }
            }

            if (! $emittedForTile) {
                yield $this->projectRow($tile, null, null, $query);
            }
        }
    }

    /**
     * Project a single row from the tile/metric/value tuple. All fields
     * in FieldWhitelist are always populated; the writer picks only the
     * requested subset.
     *
     * @return array<string, mixed>
     */
    private function projectRow(Tile $tile, ?MetricDefinition $md, ?MetricValue $mv, ExportQuery $query): array
    {
        $locale = $query->locale;

        return [
            FieldWhitelist::TILE_ID => $tile->id,
            FieldWhitelist::TILE_SLUG => $this->translate($tile, 'slug', $locale),
            FieldWhitelist::TILE_TITLE => $this->translate($tile, 'title', $locale),
            FieldWhitelist::TILE_DESCRIPTION => $this->richText($this->translate($tile, 'description', $locale), $query),
            FieldWhitelist::TILE_HINT => $this->richText($this->translate($tile, 'hint', $locale), $query),
            FieldWhitelist::TILE_TIME_GRANULARITY => $tile->time_granularity ?? 'year',

            FieldWhitelist::CATEGORY_LABELS => $this->categoryLabels($tile, $locale),
            FieldWhitelist::CATEGORY_GROUPS => $this->categoryGroupTitles($tile, $locale),

            FieldWhitelist::METRIC_KEY => $md?->metric_key,
            FieldWhitelist::METRIC_LABEL => $md ? $this->translate($md, 'label', $locale) : null,
            FieldWhitelist::METRIC_UNIT => $md ? $this->translate($md, 'unit', $locale) : null,

            FieldWhitelist::VALUE_YEAR => $this->periodYear($mv),
            FieldWhitelist::VALUE_PERIOD_KEY => $mv?->timePeriod?->period_key,
            FieldWhitelist::VALUE_GRANULARITY => $mv?->timePeriod?->granularity?->value,
            FieldWhitelist::VALUE_VALUE => $mv !== null ? (float) $mv->value : null,

            // Reserved — see FieldWhitelist constants for rationale.
            FieldWhitelist::METRIC_SOURCE => null,
            FieldWhitelist::METRIC_SOURCE_URL => null,
            FieldWhitelist::METRIC_METHODOLOGY => null,
            FieldWhitelist::METRIC_FORMULA => null,
        ];
    }

    /**
     * Read a translatable attribute in the requested locale only. No fallback:
     * a re-import stores the value as that locale's translation.
     */
    private function translate($model, string $attribute, string $locale): ?string
    {
        $value = $model->getTranslation($attribute, $locale, false);

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    /**
     * JSON keeps the stored HTML so a re-import does not lose markup; CSV is read by people.
     */
    private function richText(?string $html, ExportQuery $query): ?string
    {
        return $query->isCsv() ? $this->plainText($html) : $html;
    }

    private function plainText(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return $text !== null ? trim($text) : null;
    }

    /**
     * @return list<string>
     */
    private function categoryLabels(Tile $tile, string $locale): array
    {
        return $tile->categories
            ->map(fn ($c) => $this->translate($c, 'slug', $locale))
            ->filter(fn ($l) => $l !== null && $l !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function categoryGroupTitles(Tile $tile, string $locale): array
    {
        return $tile->categories
            ->map(fn ($c) => $c->group ? $this->translate($c->group, 'title', $locale) : null)
            ->filter(fn ($g) => $g !== null && $g !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Derive the calendar year from a metric value's time period.
     *
     * TileYear was replaced by TimePeriod (granularity + period_key).
     * For every granularity the period_key is prefixed with the 4-digit year
     * (e.g. "2023", "2023-Q1", "2023-05"), so the leading digits give the year
     * for the export's `value.year` field; `value.period_key` and
     * `value.granularity` carry the period itself.
     */
    private function periodYear(?MetricValue $mv): ?int
    {
        $key = $mv?->timePeriod?->period_key;
        if ($key === null || ! preg_match('/^(\d{4})/', $key, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    /**
     * Apply year range filter to an already-loaded metric value collection.
     */
    private function filterValuesByYear($values, ExportQuery $query)
    {
        if ($query->yearFrom === null && $query->yearTo === null) {
            return $values;
        }

        return $values->filter(function (MetricValue $mv) use ($query) {
            $year = $this->periodYear($mv);
            if ($year === null) {
                return false;
            }
            if ($query->yearFrom !== null && $year < $query->yearFrom) {
                return false;
            }
            if ($query->yearTo !== null && $year > $query->yearTo) {
                return false;
            }

            return true;
        });
    }
}
