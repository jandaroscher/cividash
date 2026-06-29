<?php

namespace App\Filament\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Provides helper methods for sorting by translatable JSON columns in Filament tables.
 *
 * This trait generates database-agnostic SQL expressions for extracting and sorting
 * by locale-specific values from JSON columns, with automatic fallback to German ('de').
 */
trait HasSortableTranslations
{
    /**
     * Allowed column names for sortable translations.
     *
     * Only these column names (or table-prefixed versions like 'category_groups.title')
     * are permitted to prevent SQL injection and enforce consistency.
     */
    private static array $allowedSortableColumns = ['title', 'slug'];

    /**
     * Build a database-agnostic expression for sorting by a translatable JSON column.
     *
     * Supports both simple column names (e.g., 'slug') and fully qualified column names
     * with table prefixes (e.g., 'category_groups.title') for use with JOIN queries.
     *
     * @param  string  $column  The column name containing JSON translations (optionally with table prefix).
     * @param  string|null  $locale  The locale to sort by (defaults to 'de' with fallback).
     * @return string SQL expression for sorting.
     *
     * @throws \InvalidArgumentException If the column name is not in the allowed list.
     */
    protected static function getSortableTranslationExpression(string $column, ?string $locale = null): string
    {
        static::validateSortableColumn($column);

        $locale = static::normalizeSortLocale($locale);
        $localePath = '$."'.$locale.'"';
        $fallbackPath = '$."de"';
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return sprintf(
                "COALESCE(NULLIF(json_extract(%s, '%s'), ''), NULLIF(json_extract(%s, '%s'), ''))",
                $column,
                $localePath,
                $column,
                $fallbackPath
            );
        }

        if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
            // Cast to json first: some translatable columns (e.g. pages.slug/title)
            // are stored as varchar, on which the ->> operator is undefined.
            return sprintf(
                "COALESCE(NULLIF(CAST(%s AS json)->>'%s', ''), NULLIF(CAST(%s AS json)->>'%s', ''))",
                $column,
                $locale,
                $column,
                'de'
            );
        }

        // MySQL/MariaDB
        return sprintf(
            "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(%s, '%s')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(%s, '%s')), ''))",
            $column,
            $localePath,
            $column,
            $fallbackPath
        );
    }

    /**
     * Return a closure for Filament's ->searchable(query: ...) that performs
     * case-insensitive search on a translatable JSON column across all available locales.
     *
     * @param  string  $column  The translatable column name (e.g., 'title', 'slug').
     * @return Closure A closure compatible with Filament's searchable query parameter.
     */
    protected static function getSearchableTranslationClosure(string $column): Closure
    {
        return function (Builder $query, string $search) use ($column): void {
            $locales = config('app.available_locales', ['de', 'en']);
            $loweredSearch = '%'.mb_strtolower($search).'%';

            $query->where(function (Builder $query) use ($column, $locales, $loweredSearch) {
                foreach ($locales as $locale) {
                    $expression = static::getSortableTranslationExpression($column, $locale);
                    $query->orWhereRaw("LOWER({$expression}) LIKE ?", [$loweredSearch]);
                }
            });
        };
    }

    /**
     * Validate that a column name is allowed for sortable translations.
     *
     * Accepts both simple column names (e.g., 'title') and table-prefixed names
     * (e.g., 'category_groups.title'). For prefixed names, only the last segment
     * is validated against the allowed list.
     *
     * @param  string  $column  The column name to validate.
     *
     * @throws \InvalidArgumentException If the column name is not allowed.
     */
    protected static function validateSortableColumn(string $column): void
    {
        // Extract the actual column name (last segment after any dots)
        $columnName = str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column;

        if (! in_array($columnName, self::$allowedSortableColumns, true)) {
            throw new \InvalidArgumentException(
                "Invalid sortable column: {$column}. Allowed columns: ".implode(', ', self::$allowedSortableColumns)
            );
        }
    }

    /**
     * Normalize the locale for sorting, ensuring it's in the allowed list.
     *
     * @param  string|null  $locale  The locale to normalize.
     * @return string The normalized locale (defaults to 'de' if invalid or not provided).
     */
    protected static function normalizeSortLocale(?string $locale = null): string
    {
        $allowedLocales = config('app.available_locales', ['de', 'en']);

        if (! is_array($allowedLocales) || $allowedLocales === []) {
            $allowedLocales = ['de', 'en'];
        }

        $locale = $locale ?: 'de';

        return in_array($locale, $allowedLocales, true) ? $locale : 'de';
    }
}
