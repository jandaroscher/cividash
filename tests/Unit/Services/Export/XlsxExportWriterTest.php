<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Unit\Services\Export;

use App\Services\Export\ExportQuery;
use App\Services\Export\FieldWhitelist;
use App\Services\Export\Writers\XlsxExportWriter;
use DateTimeImmutable;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;
use ZipArchive;

class XlsxExportWriterTest extends TestCase
{
    private ?string $file = null;

    protected function tearDown(): void
    {
        if ($this->file !== null && is_file($this->file)) {
            unlink($this->file);
        }
        parent::tearDown();
    }

    /**
     * Writes the export to a file and returns it as [sheet name => rows].
     *
     * @return array<string, list<list<mixed>>>
     */
    private function export(ExportQuery $query, array $rows, array $meta = [], ?XlsxExportWriter $writer = null): array
    {
        $this->file = tempnam(sys_get_temp_dir(), 'xlsx-test-');
        $stream = fopen($this->file, 'wb');

        $writer ??= new XlsxExportWriter;
        $writer->open($stream, $query, $meta + [
            'schema_version' => '1.0',
            'generated_at' => '2026-11-25T10:30:00+01:00',
        ]);
        foreach ($rows as $row) {
            $writer->writeRow($stream, $row);
        }
        $writer->close($stream);
        fclose($stream);

        $reader = new Reader;
        $reader->open($this->file);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();

        return $sheets;
    }

    private function sheetXml(string $name): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->file) === true);
        $xml = $zip->getFromName($name);
        $zip->close();

        return (string) $xml;
    }

    public function test_data_sheet_has_localized_header_and_typed_values(): void
    {
        $query = new ExportQuery(
            format: ExportQuery::FORMAT_XLSX,
            locale: 'de',
            fields: [FieldWhitelist::TILE_ID, FieldWhitelist::TILE_TITLE, FieldWhitelist::CATEGORY_LABELS, FieldWhitelist::VALUE_YEAR, FieldWhitelist::VALUE_VALUE, FieldWhitelist::METRIC_SOURCE],
        );

        $sheets = $this->export($query, [[
            FieldWhitelist::TILE_ID => 7,
            FieldWhitelist::TILE_TITLE => 'Mobilität',
            FieldWhitelist::CATEGORY_LABELS => ['Verkehr', 'Umwelt'],
            FieldWhitelist::VALUE_YEAR => 2021,
            FieldWhitelist::VALUE_VALUE => 95.25,
            FieldWhitelist::METRIC_SOURCE => null,
        ]]);

        $this->assertSame(['Daten', 'Info'], array_keys($sheets));
        $this->assertSame(['Kachel-ID', 'Kachel', 'Kategorien', 'Jahr', 'Wert', 'Quelle'], $sheets['Daten'][0]);

        [$id, $title, $labels, $year, $value] = $sheets['Daten'][1];
        $this->assertSame(7, $id);
        $this->assertSame('Mobilität', $title);
        $this->assertSame('Verkehr, Umwelt', $labels);
        $this->assertSame(2021, $year);
        $this->assertSame(95.25, $value);
        $this->assertSame('', $sheets['Daten'][1][5] ?? '');
    }

    public function test_header_row_is_frozen_and_filterable(): void
    {
        $query = new ExportQuery(format: ExportQuery::FORMAT_XLSX, locale: 'en', fields: [FieldWhitelist::TILE_TITLE, FieldWhitelist::VALUE_VALUE]);

        $sheets = $this->export($query, [
            [FieldWhitelist::TILE_TITLE => 'A', FieldWhitelist::VALUE_VALUE => 1.5],
            [FieldWhitelist::TILE_TITLE => 'B', FieldWhitelist::VALUE_VALUE => 2],
        ]);

        $this->assertSame(['Data', 'Info'], array_keys($sheets));
        $xml = $this->sheetXml('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('<autoFilter ref="A1:B3"', $xml);
        $this->assertStringContainsString('state="frozen"', $xml);
    }

    public function test_text_starting_with_equals_sign_is_not_written_as_formula(): void
    {
        $query = new ExportQuery(format: ExportQuery::FORMAT_XLSX, locale: 'de', fields: [FieldWhitelist::TILE_TITLE]);

        $sheets = $this->export($query, [[FieldWhitelist::TILE_TITLE => '=HYPERLINK("https://example.org","x")']]);

        $this->assertSame('=HYPERLINK("https://example.org","x")', $sheets['Daten'][1][0]);
        $this->assertStringNotContainsString('<f>', $this->sheetXml('xl/worksheets/sheet1.xml'));
    }

    public function test_info_sheet_lists_metadata_with_a_real_date(): void
    {
        $query = new ExportQuery(format: ExportQuery::FORMAT_XLSX, locale: 'de', fields: [FieldWhitelist::TILE_TITLE]);

        $sheets = $this->export($query, [], [
            'tenant' => ['id' => 1, 'slug' => 'default', 'name' => 'Default Dashboard'],
            'filter' => ['year_from' => 2020],
        ]);

        $info = array_column($sheets['Info'], 1, 0);
        $this->assertSame('1.0', $info['Schema-Version']);
        $this->assertInstanceOf(DateTimeImmutable::class, $info['Erstellt am']);
        $this->assertSame('2026-11-25 10:30', $info['Erstellt am']->format('Y-m-d H:i'));
        $this->assertSame('Default Dashboard (default)', $info['Dashboard']);
        $this->assertSame('{"year_from":2020}', $info['Filter']);
        // Empty export still has the header row.
        $this->assertCount(1, $sheets['Daten']);
    }

    public function test_overlong_text_is_cut_to_the_excel_cell_limit(): void
    {
        $query = new ExportQuery(format: ExportQuery::FORMAT_XLSX, locale: 'de', fields: [FieldWhitelist::TILE_DESCRIPTION]);

        $sheets = $this->export($query, [[FieldWhitelist::TILE_DESCRIPTION => str_repeat('ä', 40000)]]);

        $this->assertSame(32767, mb_strlen($sheets['Daten'][1][0]));
    }

    public function test_emoji_text_is_cut_by_utf16_units_without_splitting_a_character(): void
    {
        $query = new ExportQuery(format: ExportQuery::FORMAT_XLSX, locale: 'de', fields: [FieldWhitelist::TILE_DESCRIPTION]);

        // Each emoji is two UTF-16 units: the limit of 32,767 falls inside the
        // 16,384th one, which must be dropped whole.
        $sheets = $this->export($query, [[FieldWhitelist::TILE_DESCRIPTION => str_repeat('🌱', 20000)]]);

        $this->assertSame(str_repeat('🌱', 16383), $sheets['Daten'][1][0]);
    }

    public function test_characters_xml_forbids_are_dropped_so_excel_can_open_the_file(): void
    {
        $query = new ExportQuery(format: ExportQuery::FORMAT_XLSX, locale: 'de', fields: [FieldWhitelist::TILE_TITLE]);

        $sheets = $this->export($query, [[FieldWhitelist::TILE_TITLE => "c\u{FFFE}d\u{FFFF}e"]]);

        $this->assertSame('cde', $sheets['Daten'][1][0]);
        $doc = new \DOMDocument;
        $this->assertTrue($doc->loadXML($this->sheetXml('xl/worksheets/sheet1.xml')));
    }

    public function test_an_aborted_export_leaves_no_temporary_files(): void
    {
        $before = glob(sys_get_temp_dir().'/*') ?: [];

        $writer = new XlsxExportWriter;
        $stream = fopen('php://memory', 'w+b');
        $writer->open($stream, new ExportQuery(format: ExportQuery::FORMAT_XLSX, locale: 'de', fields: [FieldWhitelist::TILE_TITLE]), []);
        $writer->writeRow($stream, [FieldWhitelist::TILE_TITLE => 'A']);
        unset($writer);
        fclose($stream);

        $this->assertSame([], array_values(array_diff(glob(sys_get_temp_dir().'/*') ?: [], $before)));
    }

    public function test_rows_beyond_the_sheet_limit_continue_on_sheets_with_header_and_filter(): void
    {
        $query = new ExportQuery(format: ExportQuery::FORMAT_XLSX, locale: 'de', fields: [FieldWhitelist::TILE_TITLE, FieldWhitelist::VALUE_VALUE]);
        $rows = array_map(fn (int $i) => [FieldWhitelist::TILE_TITLE => "T$i", FieldWhitelist::VALUE_VALUE => $i], range(1, 5));

        // Three rows per sheet: the header plus two values.
        $sheets = $this->export($query, $rows, [], new XlsxExportWriter(maxRowsPerSheet: 3));

        $this->assertSame(['Daten', 'Daten 2', 'Daten 3', 'Info'], array_keys($sheets));
        $this->assertSame([['Kachel', 'Wert'], ['T1', 1], ['T2', 2]], $sheets['Daten']);
        $this->assertSame([['Kachel', 'Wert'], ['T3', 3], ['T4', 4]], $sheets['Daten 2']);
        $this->assertSame([['Kachel', 'Wert'], ['T5', 5]], $sheets['Daten 3']);

        foreach (['sheet1' => 'A1:B3', 'sheet2' => 'A1:B3', 'sheet3' => 'A1:B2'] as $file => $range) {
            $xml = $this->sheetXml("xl/worksheets/$file.xml");
            $this->assertStringContainsString('<autoFilter ref="'.$range.'"', $xml, $file);
            $this->assertStringContainsString('state="frozen"', $xml, $file);
        }
    }
}
