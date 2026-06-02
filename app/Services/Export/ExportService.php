<?php

namespace App\Services\Export;

use App\Models\Tenant;
use App\Services\Export\Writers\Contracts\ExportWriter;
use App\Services\Export\Writers\CsvExportWriter;
use App\Services\Export\Writers\JsonExportWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Coordinates a single export: picks the writer for the requested format,
 * builds the streaming response, and drives the collector generator.
 *
 * Stays decoupled from HTTP request parsing — the controller builds an
 * ExportQuery and hands it in.
 */
class ExportService
{
    public const SCHEMA_VERSION = '1.0';

    public function __construct(
        private readonly TileExportCollector $collector,
    ) {}

    public function stream(ExportQuery $query, ?Tenant $tenant, string $filenameBase): StreamedResponse
    {
        $writer = $this->writerFor($query->format);

        $filename = $this->buildFilename($filenameBase, $writer->extension());

        $meta = [
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at' => now()->toIso8601String(),
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name ?? $tenant->slug,
            ] : null,
            'filter' => $this->filterMeta($query),
        ];

        return new StreamedResponse(
            function () use ($writer, $query, $meta) {
                $stream = fopen('php://output', 'wb');
                $writer->open($stream, $query, $meta);
                foreach ($this->collector->collect($query) as $row) {
                    $writer->writeRow($stream, $row);
                }
                $writer->close($stream);
                fclose($stream);
            },
            200,
            [
                'Content-Type' => $writer->mimeType(),
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
                'X-Export-Schema-Version' => self::SCHEMA_VERSION,
            ]
        );
    }

    private function writerFor(string $format): ExportWriter
    {
        return match ($format) {
            ExportQuery::FORMAT_JSON => new JsonExportWriter,
            ExportQuery::FORMAT_CSV => new CsvExportWriter,
            default => throw new \InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }

    private function buildFilename(string $base, string $extension): string
    {
        $safeBase = preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $base) ?? 'export';
        $safeBase = trim($safeBase, '-') ?: 'export';
        $timestamp = now()->format('Ymd-His');

        return sprintf('%s-%s.%s', $safeBase, $timestamp, $extension);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function filterMeta(ExportQuery $query): ?array
    {
        $filter = [];
        if ($query->tileSlug !== null) {
            $filter['tile_slug'] = $query->tileSlug;
        }
        if (! empty($query->tileIds)) {
            $filter['tile_ids'] = $query->tileIds;
        }
        if (! empty($query->categoryKeys)) {
            $filter['category_keys'] = $query->categoryKeys;
        }
        if ($query->yearFrom !== null) {
            $filter['year_from'] = $query->yearFrom;
        }
        if ($query->yearTo !== null) {
            $filter['year_to'] = $query->yearTo;
        }

        return $filter === [] ? null : $filter;
    }
}
