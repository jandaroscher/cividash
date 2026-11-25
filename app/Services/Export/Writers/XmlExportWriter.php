<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Services\Export\Writers;

use App\Services\Export\ExportQuery;
use App\Services\Export\Writers\Contracts\ExportWriter;
use XMLWriter;

/**
 * Streams an XML document in the `urn:cividash:export:1.0` namespace.
 *
 * Field elements carry the same names as the JSON keys (`tile.id`,
 * `value.value`, …). Lists become `<item>` children and empty values
 * `xsi:nil="true"`, so every selected field is present in each row.
 * The schema lives in docs/export/schemas/v1/export.xsd.
 */
class XmlExportWriter implements ExportWriter
{
    public const NAMESPACE = 'urn:cividash:export:1.0';

    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    private XMLWriter $xml;

    private ExportQuery $query;

    public function mimeType(): string
    {
        return 'application/xml; charset=UTF-8';
    }

    public function extension(): string
    {
        return 'xml';
    }

    public function open($stream, ExportQuery $query, array $meta): void
    {
        $this->query = $query;
        $this->xml = new XMLWriter;
        $this->xml->openMemory();
        $this->xml->setIndent(true);
        $this->xml->setIndentString('  ');
        $this->xml->startDocument('1.0', 'UTF-8');

        $this->xml->startElementNs(null, 'export', self::NAMESPACE);
        $this->xml->writeAttributeNs('xmlns', 'xsi', null, self::XSI);
        $this->xml->writeAttribute('schema_version', (string) ($meta['schema_version'] ?? '1.0'));

        $this->xml->writeElement('generated_at', (string) ($meta['generated_at'] ?? now()->toIso8601String()));

        $tenant = $meta['tenant'] ?? null;
        if (is_array($tenant)) {
            $this->xml->startElement('tenant');
            $this->xml->writeAttribute('id', (string) $tenant['id']);
            $this->xml->writeAttribute('slug', $this->clean((string) $tenant['slug']));
            $this->xml->text($this->clean((string) ($tenant['name'] ?? $tenant['slug'])));
            $this->xml->endElement();
        }

        $this->xml->writeElement('locale', $query->locale);

        $filter = $meta['filter'] ?? null;
        if (is_array($filter)) {
            $this->xml->startElement('filter');
            foreach (['tile_slug', 'tile_ids', 'category_keys', 'year_from', 'year_to'] as $key) {
                if (array_key_exists($key, $filter)) {
                    $this->writeValue($key, $filter[$key]);
                }
            }
            $this->xml->endElement();
        }

        $this->xml->startElement('fields');
        foreach ($query->fields as $field) {
            $this->xml->writeElement('field', $field);
        }
        $this->xml->endElement();

        $this->xml->startElement('rows');
        fwrite($stream, $this->xml->flush());
    }

    public function writeRow($stream, array $row): void
    {
        $this->xml->startElement('row');
        foreach ($this->query->fields as $field) {
            $this->writeValue($field, $row[$field] ?? null);
        }
        $this->xml->endElement();

        fwrite($stream, $this->xml->flush());
    }

    public function close($stream): void
    {
        $this->xml->endElement(); // rows
        $this->xml->endElement(); // export
        $this->xml->endDocument();

        fwrite($stream, $this->xml->flush());
    }

    private function writeValue(string $name, mixed $value): void
    {
        $this->xml->startElement($name);

        if ($value === null) {
            $this->xml->writeAttributeNs('xsi', 'nil', null, 'true');
        } elseif (is_array($value)) {
            foreach ($value as $item) {
                $this->xml->writeElement('item', $this->scalar($item));
            }
        } else {
            $this->xml->text($this->scalar($value));
        }

        $this->xml->endElement();
    }

    private function scalar(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            // json_encode keeps the shortest round-trip form, like the JSON export.
            is_float($value) => json_encode($value, JSON_THROW_ON_ERROR),
            default => $this->clean((string) $value),
        };
    }

    /**
     * XML 1.0 forbids most control characters; XMLWriter would emit them
     * verbatim and leave the document unparseable.
     */
    private function clean(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');

        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
    }
}
