<?php

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
                                $vq->where('is_active', true)->with('tileYear');
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
                yield $this->projectRow($tile, null, null, $query->locale);

                continue;
            }

            $emittedForTile = false;

            foreach ($metricDefinitions as $md) {
                /** @var MetricDefinition $md */
                $values = $this->filterValuesByYear($md->metricValues, $query);

                if ($values->isEmpty()) {
                    yield $this->projectRow($tile, $md, null, $query->locale);
                    $emittedForTile = true;

                    continue;
                }

                foreach ($values as $mv) {
                    /** @var MetricValue $mv */
                    yield $this->projectRow($tile, $md, $mv, $query->locale);
                    $emittedForTile = true;
                }
            }

            if (! $emittedForTile) {
                yield $this->projectRow($tile, null, null, $query->locale);
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
    private function projectRow(Tile $tile, ?MetricDefinition $md, ?MetricValue $mv, string $locale): array
    {
        return [
            FieldWhitelist::TILE_ID => $tile->id,
            FieldWhitelist::TILE_SLUG => $this->translate($tile, 'slug', $locale),
            FieldWhitelist::TILE_TITLE => $this->translate($tile, 'title', $locale),
            FieldWhitelist::TILE_DESCRIPTION => $this->plainText($this->translate($tile, 'description', $locale)),
            FieldWhitelist::TILE_HINT => $this->plainText($this->translate($tile, 'hint', $locale)),
            FieldWhitelist::TILE_POSITION => $tile->position,

            FieldWhitelist::CATEGORY_KEYS => $this->categoryKeys($tile),
            FieldWhitelist::CATEGORY_LABELS => $this->categoryLabels($tile, $locale),
            FieldWhitelist::CATEGORY_GROUPS => $this->categoryGroupTitles($tile, $locale),

            FieldWhitelist::METRIC_KEY => $md?->metric_key,
            FieldWhitelist::METRIC_LABEL => $md ? $this->translate($md, 'label', $locale) : null,
            FieldWhitelist::METRIC_UNIT => $md ? $this->translate($md, 'unit', $locale) : null,
            FieldWhitelist::METRIC_INDICATOR_TYPE => $md?->indicator_type,

            FieldWhitelist::VALUE_YEAR => $mv?->tileYear?->year,
            FieldWhitelist::VALUE_VALUE => $mv !== null ? (float) $mv->value : null,
            FieldWhitelist::VALUE_SORT_ORDER => $mv?->sort_order,

            // Reserved — see FieldWhitelist constants for rationale.
            FieldWhitelist::METRIC_SOURCE => null,
            FieldWhitelist::METRIC_SOURCE_URL => null,
            FieldWhitelist::METRIC_METHODOLOGY => null,
            FieldWhitelist::METRIC_FORMULA => null,
        ];
    }

    /**
     * Read a translatable attribute with locale → 'de' → 'en' fallback.
     */
    private function translate($model, string $attribute, string $locale): ?string
    {
        $value = $model->getTranslation($attribute, $locale, false);
        if ($value !== null && $value !== '') {
            return (string) $value;
        }

        foreach (['de', 'en'] as $fallback) {
            if ($fallback === $locale) {
                continue;
            }
            $value = $model->getTranslation($attribute, $fallback, false);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
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
    private function categoryKeys(Tile $tile): array
    {
        return $tile->categories
            ->map(fn ($c) => $c->key)
            ->filter(fn ($k) => $k !== null && $k !== '')
            ->values()
            ->all();
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
     * Apply year range filter to an already-loaded metric value collection.
     */
    private function filterValuesByYear($values, ExportQuery $query)
    {
        if ($query->yearFrom === null && $query->yearTo === null) {
            return $values;
        }

        return $values->filter(function (MetricValue $mv) use ($query) {
            $year = $mv->tileYear?->year;
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
