<?php

namespace Tests\Unit\Services\Export;

use App\Services\Export\ExportQuery;
use App\Services\Export\FieldWhitelist;
use App\Services\Export\Writers\CsvExportWriter;
use Tests\TestCase;

class CsvExportWriterTest extends TestCase
{
    private function drainWriter(ExportQuery $query, array $rows): string
    {
        $writer = new CsvExportWriter;
        $stream = fopen('php://memory', 'w+b');

        $writer->open($stream, $query, ['schema_version' => '1.0']);
        foreach ($rows as $row) {
            $writer->writeRow($stream, $row);
        }
        $writer->close($stream);

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    public function test_output_starts_with_utf8_bom(): void
    {
        $query = new ExportQuery(
            format: ExportQuery::FORMAT_CSV,
            locale: 'de',
            fields: [FieldWhitelist::TILE_ID, FieldWhitelist::TILE_TITLE],
        );

        $output = $this->drainWriter($query, []);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $output);
    }

    public function test_header_is_localized_and_uses_semicolon_delimiter(): void
    {
        $query = new ExportQuery(
            format: ExportQuery::FORMAT_CSV,
            locale: 'de',
            fields: [FieldWhitelist::TILE_ID, FieldWhitelist::METRIC_LABEL, FieldWhitelist::VALUE_YEAR],
        );

        $output = $this->drainWriter($query, []);
        $lines = preg_split('/\r?\n/', substr($output, 3));

        $this->assertSame('Kachel-ID;Metrik;Jahr', $lines[0]);
    }

    public function test_english_locale_switches_column_labels(): void
    {
        $query = new ExportQuery(
            format: ExportQuery::FORMAT_CSV,
            locale: 'en',
            fields: [FieldWhitelist::TILE_TITLE, FieldWhitelist::VALUE_YEAR],
        );

        $output = $this->drainWriter($query, []);
        $lines = preg_split('/\r?\n/', substr($output, 3));

        $this->assertSame('Tile;Year', $lines[0]);
    }

    public function test_values_with_semicolon_quote_and_newline_are_escaped(): void
    {
        $query = new ExportQuery(
            format: ExportQuery::FORMAT_CSV,
            locale: 'de',
            fields: [FieldWhitelist::TILE_TITLE, FieldWhitelist::TILE_DESCRIPTION],
        );

        $output = $this->drainWriter($query, [
            [
                FieldWhitelist::TILE_TITLE => 'A;B',
                FieldWhitelist::TILE_DESCRIPTION => 'Line1'."\n".'Line2 with "quotes"',
            ],
        ]);

        $this->assertStringContainsString('"A;B"', $output);
        $this->assertStringContainsString('"Line1', $output);
        // fputcsv escapes embedded double quotes by doubling them.
        $this->assertStringContainsString('""quotes""', $output);
    }

    public function test_null_values_become_empty_cells_and_arrays_are_comma_joined(): void
    {
        $query = new ExportQuery(
            format: ExportQuery::FORMAT_CSV,
            locale: 'de',
            fields: [FieldWhitelist::METRIC_KEY, FieldWhitelist::CATEGORY_LABELS, FieldWhitelist::METRIC_SOURCE],
        );

        $output = $this->drainWriter($query, [
            [
                FieldWhitelist::METRIC_KEY => 'co2',
                FieldWhitelist::CATEGORY_LABELS => ['Verkehr', 'Umwelt'],
                FieldWhitelist::METRIC_SOURCE => null,
            ],
        ]);

        $lines = array_values(array_filter(preg_split('/\r?\n/', substr($output, 3))));
        $this->assertSame('co2;"Verkehr, Umwelt";', $lines[1]);
    }
}
