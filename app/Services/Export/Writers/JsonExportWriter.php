<?php

namespace App\Services\Export\Writers;

use App\Services\Export\ExportQuery;
use App\Services\Export\Writers\Contracts\ExportWriter;

/**
 * Streams a JSON envelope with metadata header and an incrementally
 * emitted `data` array. Each row is encoded and separated by commas.
 */
class JsonExportWriter implements ExportWriter
{
    private bool $firstRow = true;

    private ExportQuery $query;

    public function mimeType(): string
    {
        return 'application/json';
    }

    public function extension(): string
    {
        return 'json';
    }

    public function open($stream, ExportQuery $query, array $meta): void
    {
        $this->query = $query;
        $this->firstRow = true;

        $envelope = [
            'schema_version' => $meta['schema_version'] ?? '1.0',
            'generated_at' => $meta['generated_at'] ?? now()->toIso8601String(),
            'tenant' => $meta['tenant'] ?? null,
            'locale' => $query->locale,
            'fields' => $query->fields,
            'filter' => $meta['filter'] ?? null,
        ];

        fwrite($stream, '{');
        foreach ($envelope as $key => $value) {
            fwrite($stream, json_encode($key, JSON_THROW_ON_ERROR).':'.json_encode($value, JSON_THROW_ON_ERROR).',');
        }
        fwrite($stream, '"data":[');
    }

    public function writeRow($stream, array $row): void
    {
        if (! $this->firstRow) {
            fwrite($stream, ',');
        }

        // Project the full collector row down to the requested fields, preserving
        // the canonical order so JSON and CSV output line up.
        $projected = [];
        foreach ($this->query->fields as $field) {
            $projected[$field] = $row[$field] ?? null;
        }

        fwrite($stream, json_encode($projected, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->firstRow = false;
    }

    public function close($stream): void
    {
        fwrite($stream, ']}');
    }
}
