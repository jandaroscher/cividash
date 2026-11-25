<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Unit\Services\Export;

use App\Services\Export\ExportQuery;
use App\Services\Export\FieldWhitelist;
use App\Services\Export\Writers\XmlExportWriter;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class XmlExportWriterTest extends TestCase
{
    private const XSD = __DIR__.'/../../../../docs/export/schemas/v1/export.xsd';

    private function drainWriter(ExportQuery $query, array $rows, array $meta = []): string
    {
        $writer = new XmlExportWriter;
        $stream = fopen('php://memory', 'w+b');

        $writer->open($stream, $query, $meta + [
            'schema_version' => '1.0',
            'generated_at' => '2026-11-25T10:00:00+01:00',
        ]);
        foreach ($rows as $row) {
            $writer->writeRow($stream, $row);
        }
        $writer->close($stream);

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    private function load(string $xml): DOMXPath
    {
        $doc = new DOMDocument;
        $this->assertTrue($doc->loadXML($xml), 'Output must be well-formed XML');

        libxml_use_internal_errors(true);
        $valid = $doc->schemaValidate(self::XSD);
        $errors = array_map(fn ($e) => trim($e->message), libxml_get_errors());
        libxml_clear_errors();
        $this->assertTrue($valid, 'Output must match export.xsd: '.implode('; ', $errors));

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('e', XmlExportWriter::NAMESPACE);

        return $xpath;
    }

    private function fullRow(array $overrides = []): array
    {
        return $overrides + [
            FieldWhitelist::TILE_ID => 7,
            FieldWhitelist::TILE_SLUG => 'mobilitaet',
            FieldWhitelist::TILE_TITLE => 'Mobilität & Verkehr',
            FieldWhitelist::TILE_DESCRIPTION => '<p>Rad <b>und</b> Bus</p>',
            FieldWhitelist::TILE_HINT => null,
            FieldWhitelist::TILE_TIME_GRANULARITY => 'year',
            FieldWhitelist::CATEGORY_LABELS => ['Verkehr', 'Umwelt'],
            FieldWhitelist::CATEGORY_GROUPS => [],
            FieldWhitelist::METRIC_KEY => 'co2',
            FieldWhitelist::METRIC_LABEL => 'CO₂-Emissionen',
            FieldWhitelist::METRIC_UNIT => 't',
            FieldWhitelist::VALUE_YEAR => 2021,
            FieldWhitelist::VALUE_PERIOD_KEY => '2021',
            FieldWhitelist::VALUE_GRANULARITY => 'year',
            FieldWhitelist::VALUE_VALUE => 95.25,
            FieldWhitelist::METRIC_SOURCE => null,
            FieldWhitelist::METRIC_SOURCE_URL => null,
            FieldWhitelist::METRIC_METHODOLOGY => null,
            FieldWhitelist::METRIC_FORMULA => null,
        ];
    }

    public function test_full_export_is_valid_against_the_schema_and_keeps_values(): void
    {
        $query = new ExportQuery(format: ExportQuery::FORMAT_XML, locale: 'de', fields: FieldWhitelist::all());

        $xpath = $this->load($this->drainWriter($query, [$this->fullRow()], [
            'tenant' => ['id' => 1, 'slug' => 'default', 'name' => 'Default Dashboard'],
            'filter' => ['tile_ids' => [7], 'year_from' => 2020],
        ]));

        $this->assertSame('1.0', $xpath->evaluate('string(/e:export/@schema_version)'));
        $this->assertSame('default', $xpath->evaluate('string(/e:export/e:tenant/@slug)'));
        $this->assertSame('7', $xpath->evaluate('string(/e:export/e:filter/e:tile_ids/e:item)'));
        $this->assertSame(19.0, $xpath->evaluate('count(/e:export/e:fields/e:field)'));

        $row = '/e:export/e:rows/e:row[1]';
        $this->assertSame('Mobilität & Verkehr', $xpath->evaluate("string($row/e:tile.title)"));
        $this->assertSame('<p>Rad <b>und</b> Bus</p>', $xpath->evaluate("string($row/e:tile.description)"));
        $this->assertSame('95.25', $xpath->evaluate("string($row/e:value.value)"));
        $this->assertSame('2021', $xpath->evaluate("string($row/e:value.year)"));
        $this->assertSame(['Verkehr', 'Umwelt'], array_map(
            fn ($n) => $n->textContent,
            iterator_to_array($xpath->query("$row/e:category.labels/e:item")),
        ));
    }

    public function test_null_values_are_marked_nil_and_unselected_fields_are_absent(): void
    {
        $query = new ExportQuery(
            format: ExportQuery::FORMAT_XML,
            locale: 'en',
            fields: [FieldWhitelist::TILE_TITLE, FieldWhitelist::METRIC_SOURCE],
        );

        $xpath = $this->load($this->drainWriter($query, [$this->fullRow()]));

        $row = '/e:export/e:rows/e:row[1]';
        $this->assertSame(
            'true',
            $xpath->evaluate("string($row/e:metric.source/@*[local-name()='nil'])"),
        );
        $this->assertSame(2.0, $xpath->evaluate("count($row/*)"));
        $this->assertSame(0.0, $xpath->evaluate('count(/e:export/e:tenant)'));
    }

    public function test_empty_export_is_valid(): void
    {
        $query = new ExportQuery(format: ExportQuery::FORMAT_XML, locale: 'de', fields: FieldWhitelist::all());

        $xpath = $this->load($this->drainWriter($query, []));

        $this->assertSame(0.0, $xpath->evaluate('count(/e:export/e:rows/e:row)'));
    }

    public function test_control_characters_are_dropped_so_the_document_stays_parseable(): void
    {
        $query = new ExportQuery(format: ExportQuery::FORMAT_XML, locale: 'de', fields: [FieldWhitelist::TILE_TITLE]);

        $xpath = $this->load($this->drainWriter($query, [[FieldWhitelist::TILE_TITLE => "A\x01B\x0BC"]]));

        $this->assertSame('ABC', $xpath->evaluate('string(/e:export/e:rows/e:row[1]/e:tile.title)'));
    }
}
