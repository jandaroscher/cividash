<?php

namespace App\Services\Export;

class FieldWhitelist
{
    public const TILE_ID = 'tile.id';

    public const TILE_SLUG = 'tile.slug';

    public const TILE_TITLE = 'tile.title';

    public const TILE_DESCRIPTION = 'tile.description';

    public const TILE_HINT = 'tile.hint';

    public const CATEGORY_LABELS = 'category.labels';

    public const CATEGORY_GROUPS = 'category.groups';

    public const METRIC_LABEL = 'metric.label';

    public const METRIC_UNIT = 'metric.unit';

    public const VALUE_YEAR = 'value.year';

    public const VALUE_VALUE = 'value.value';

    // Reserved placeholder fields - always null in v1.0. The export schema
    // keeps these keys so that structured source/methodology/formula data
    // can be added later without changing the exported field list.
    public const METRIC_SOURCE = 'metric.source';

    public const METRIC_SOURCE_URL = 'metric.source_url';

    public const METRIC_METHODOLOGY = 'metric.methodology';

    public const METRIC_FORMULA = 'metric.formula';

    /**
     * All fields that exist in the v1.0 schema, in canonical column order.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::TILE_ID,
            self::TILE_SLUG,
            self::TILE_TITLE,
            self::TILE_DESCRIPTION,
            self::TILE_HINT,
            self::CATEGORY_LABELS,
            self::CATEGORY_GROUPS,
            self::METRIC_LABEL,
            self::METRIC_UNIT,
            self::VALUE_YEAR,
            self::VALUE_VALUE,
            self::METRIC_SOURCE,
            self::METRIC_SOURCE_URL,
            self::METRIC_METHODOLOGY,
            self::METRIC_FORMULA,
        ];
    }

    /**
     * Default set returned when the caller did not specify fields explicitly.
     *
     * @return list<string>
     */
    public static function defaults(): array
    {
        return self::all();
    }

    /**
     * Reject unknown fields and return the intersection of the requested list
     * with the whitelist, preserving the canonical column order.
     *
     * @param  list<string>  $requested
     * @return list<string>
     */
    public static function filter(array $requested): array
    {
        $allowed = array_flip(self::all());
        $filtered = array_values(array_filter($requested, fn (string $f) => isset($allowed[$f])));

        return array_values(array_intersect(self::all(), $filtered));
    }

    /**
     * @param  list<string>  $requested
     * @return list<string> Fields from the request that are not on the whitelist.
     */
    public static function rejected(array $requested): array
    {
        $allowed = array_flip(self::all());

        return array_values(array_filter($requested, fn (string $f) => ! isset($allowed[$f])));
    }
}
