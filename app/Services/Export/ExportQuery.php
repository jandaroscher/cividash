<?php

namespace App\Services\Export;

/**
 * Immutable DTO describing a single export request.
 *
 * Holds the format, locale, selected fields (already whitelisted) and
 * optional filter parameters. Created by ExportController after
 * ExportQueryRequest validation.
 */
class ExportQuery
{
    public const FORMAT_JSON = 'json';

    public const FORMAT_CSV = 'csv';

    /**
     * @param  list<string>  $fields  Already filtered via FieldWhitelist::filter().
     * @param  list<int>  $tileIds
     * @param  list<string>  $categoryKeys
     */
    public function __construct(
        public readonly string $format,
        public readonly string $locale,
        public readonly array $fields,
        public readonly ?string $tileSlug = null,
        public readonly array $tileIds = [],
        public readonly array $categoryKeys = [],
        public readonly ?int $yearFrom = null,
        public readonly ?int $yearTo = null,
    ) {}

    public function isJson(): bool
    {
        return $this->format === self::FORMAT_JSON;
    }

    public function isCsv(): bool
    {
        return $this->format === self::FORMAT_CSV;
    }
}
