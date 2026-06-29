<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Builds database-agnostic SQL expressions for reading a locale value out of a
 * translatable JSON column (e.g. `slug`, `title`).
 *
 * PostgreSQL does not provide MySQL's `JSON_EXTRACT`, and SQLite/MySQL/MariaDB
 * each expose JSON access differently. This trait centralises the per-driver
 * dialect so query builders can stay portable.
 *
 * The locale is validated against a whitelist before being interpolated into the
 * SQL string. Never pass unvalidated user input as the `$locale` argument.
 */
trait BuildsJsonLocaleExpressions
{
    /**
     * Locales allowed to be interpolated into raw JSON path expressions.
     */
    private static array $jsonLocaleWhitelist = ['de', 'en'];

    /**
     * Return a raw SQL expression that extracts the given locale's scalar value
     * from a translatable JSON column, normalised to text across all drivers.
     *
     * @param  string  $column  The JSON column name (must be a trusted identifier).
     * @param  string  $locale  The locale key (validated against the whitelist).
     * @return string SQL fragment usable inside whereRaw()/orderByRaw().
     *
     * @throws \InvalidArgumentException If the locale is not whitelisted.
     */
    protected function jsonLocaleExpression(string $column, string $locale): string
    {
        if (! in_array($locale, self::$jsonLocaleWhitelist, true)) {
            throw new \InvalidArgumentException("Unsupported locale [{$locale}] for JSON expression.");
        }

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
            // PostgreSQL: ->> returns the JSON object field as text. Some translatable
            // columns are stored as `varchar` (JSON-encoded text) rather than `json`,
            // so cast to json first — a no-op for genuine json columns, and required
            // for varchar ones (the ->> operator is undefined on varchar).
            return sprintf("CAST(%s AS json)->>'%s'", $column, $locale);
        }

        if ($driver === 'sqlite') {
            // SQLite ships a json_extract() function; $."de" path syntax.
            return sprintf("json_extract(%s, '$.\"%s\"')", $column, $locale);
        }

        // MySQL / MariaDB: unquote so the comparison is against the raw scalar.
        return sprintf("JSON_UNQUOTE(JSON_EXTRACT(%s, '$.\"%s\"'))", $column, $locale);
    }
}
