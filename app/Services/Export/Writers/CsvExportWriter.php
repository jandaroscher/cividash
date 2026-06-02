<?php

namespace App\Services\Export\Writers;

use App\Services\Export\ExportQuery;
use App\Services\Export\Writers\Contracts\ExportWriter;

/**
 * Streams a CSV file with UTF-8 BOM, semicolon delimiter (Excel-DE
 * friendly) and RFC-4180-compliant escaping via PHP's fputcsv.
 *
 * The header row is localized via `__('export.columns.<field>', [], $locale)`.
 */
class CsvExportWriter implements ExportWriter
{
    private const DELIMITER = ';';

    private const ENCLOSURE = '"';

    private const ESCAPE = '';

    private ExportQuery $query;

    public function mimeType(): string
    {
        return 'text/csv; charset=UTF-8';
    }

    public function extension(): string
    {
        return 'csv';
    }

    public function open($stream, ExportQuery $query, array $meta): void
    {
        $this->query = $query;

        // UTF-8 BOM so Excel on Windows detects the encoding.
        fwrite($stream, "\xEF\xBB\xBF");

        $header = array_map(
            fn (string $field) => trans('export.columns.'.$field, [], $query->locale),
            $query->fields,
        );

        fputcsv($stream, $header, self::DELIMITER, self::ENCLOSURE, self::ESCAPE);
    }

    public function writeRow($stream, array $row): void
    {
        $ordered = array_map(
            fn (string $field) => $this->stringify($row[$field] ?? null),
            $this->query->fields,
        );

        fputcsv($stream, $ordered, self::DELIMITER, self::ENCLOSURE, self::ESCAPE);
    }

    public function close($stream): void
    {
        // No CSV footer.
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => (string) $v, $value));
        }

        return (string) $value;
    }
}
