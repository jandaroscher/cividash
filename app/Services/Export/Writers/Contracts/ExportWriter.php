<?php

namespace App\Services\Export\Writers\Contracts;

use App\Services\Export\ExportQuery;

/**
 * Contract for streaming export writers.
 *
 * Writers are stateful for the duration of a single export and receive a
 * PHP stream handle (typically php://output under a StreamedResponse).
 * v1.1 will add XmlExportWriter and XlsxExportWriter implementing this
 * same contract.
 */
interface ExportWriter
{
    public function mimeType(): string;

    public function extension(): string;

    /**
     * Write the envelope/header to the stream before any rows.
     *
     * @param  resource  $stream
     * @param  array<string, mixed>  $meta  Metadata like schema_version, tenant, generated_at
     */
    public function open($stream, ExportQuery $query, array $meta): void;

    /**
     * Write a single flat row. The row is a map keyed by FieldWhitelist
     * field names; the writer is responsible for column ordering.
     *
     * @param  resource  $stream
     * @param  array<string, mixed>  $row
     */
    public function writeRow($stream, array $row): void;

    /**
     * Write the envelope/footer after the last row.
     *
     * @param  resource  $stream
     */
    public function close($stream): void;
}
