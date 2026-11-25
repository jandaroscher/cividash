<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Services\Export\Writers;

use App\Services\Export\ExportQuery;
use App\Services\Export\Writers\Contracts\ExportWriter;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes an Excel workbook (.xlsx) with a data sheet and an info sheet.
 *
 * XLSX is a ZIP archive, so it cannot be emitted row by row: OpenSpout
 * spools rows to a temporary file (constant memory) and close() copies
 * the finished file to the stream.
 *
 * Numbers stay numeric cells, strings are always written as text cells
 * so values starting with "=" never turn into formulas.
 */
class XlsxExportWriter implements ExportWriter
{
    private const MAX_CELL_LENGTH = 32767;

    private Writer $writer;

    private ExportQuery $query;

    /** Holds the output file and OpenSpout's work folder, removed as a whole. */
    private string $workDir;

    private string $tempFile;

    /** @var list<Sheet> */
    private array $dataSheets = [];

    /** @var array<string, mixed> */
    private array $meta;

    /**
     * @param  int  $maxRowsPerSheet  Excel's row limit, header included.
     */
    public function __construct(private readonly int $maxRowsPerSheet = 1048576) {}

    public function mimeType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function extension(): string
    {
        return 'xlsx';
    }

    public function open($stream, ExportQuery $query, array $meta): void
    {
        $this->query = $query;
        $this->meta = $meta;
        $this->workDir = sys_get_temp_dir().'/export-xlsx-'.bin2hex(random_bytes(8));
        mkdir($this->workDir, 0700);
        $this->tempFile = $this->workDir.'/export.xlsx';

        $options = new Options;
        $options->setTempFolder($this->workDir);
        $this->writer = new Writer($options);
        $this->writer->openToFile($this->tempFile);

        $this->startDataSheet($this->writer->getCurrentSheet());
    }

    public function writeRow($stream, array $row): void
    {
        // Start the next sheet ourselves before OpenSpout would, so every
        // data sheet gets the header, frozen pane and autofilter.
        if ($this->writer->getCurrentSheet()->getWrittenRowCount() >= $this->maxRowsPerSheet) {
            $this->startDataSheet($this->writer->addNewSheetAndMakeItCurrent());
        }

        $cells = array_map(
            fn (string $field) => $this->cell($row[$field] ?? null),
            $this->query->fields,
        );

        $this->writer->addRow(new Row($cells));
    }

    public function close($stream): void
    {
        $lastColumn = count($this->query->fields) - 1;
        foreach ($this->dataSheets as $sheet) {
            $sheet->setAutoFilter(new AutoFilter(0, 1, $lastColumn, $sheet->getWrittenRowCount()));
        }

        $this->writeInfoSheet();
        $this->writer->setCurrentSheet($this->dataSheets[0]);
        $this->writer->close();

        $file = fopen($this->tempFile, 'rb');
        stream_copy_to_stream($file, $stream);
        fclose($file);
    }

    public function __destruct()
    {
        // Also runs when the export aborts, even inside open(), where
        // OpenSpout's own close() would leave its work folder behind.
        if (isset($this->workDir)) {
            (new Filesystem)->deleteDirectory($this->workDir);
        }
    }

    private function startDataSheet(Sheet $sheet): void
    {
        $number = count($this->dataSheets) + 1;
        $sheet->setName($this->label('sheets.data').($number > 1 ? ' '.$number : ''));
        $sheet->setSheetView((new SheetView)->setFreezeRow(2));
        $this->dataSheets[] = $sheet;

        $header = array_map(
            fn (string $field) => trans('export.columns.'.$field, [], $this->query->locale),
            $this->query->fields,
        );
        $this->writer->addRow(Row::fromValues($header, (new Style)->setFontBold()));
    }

    private function writeInfoSheet(): void
    {
        $this->writer->addNewSheetAndMakeItCurrent()->setName($this->label('sheets.info'));

        $bold = (new Style)->setFontBold();
        $tenant = $this->meta['tenant'] ?? null;
        $filter = $this->meta['filter'] ?? null;
        $generatedAt = CarbonImmutable::parse($this->meta['generated_at'] ?? now());

        $rows = [
            'schema_version' => $this->cell((string) ($this->meta['schema_version'] ?? '1.0')),
            'generated_at' => new DateTimeCell($generatedAt->toDateTimeImmutable(), (new Style)->setFormat('yyyy-mm-dd hh:mm:ss')),
            'tenant' => $this->cell(is_array($tenant) ? sprintf('%s (%s)', $tenant['name'] ?? $tenant['slug'], $tenant['slug']) : null),
            'locale' => $this->cell($this->query->locale),
            'filter' => $this->cell(is_array($filter) ? json_encode($filter, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null),
        ];

        foreach ($rows as $key => $value) {
            $this->writer->addRow(new Row([new StringCell($this->label('info.'.$key), $bold), $value]));
        }
    }

    private function cell(mixed $value): Cell
    {
        if (is_int($value) || is_float($value)) {
            return new NumericCell($value, null);
        }
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }
        if (is_array($value)) {
            $value = implode(', ', array_map(fn ($v) => (string) $v, $value));
        }
        if ($value === null || $value === '') {
            return new EmptyCell(null, null);
        }

        return new StringCell($this->fitCell(XmlSafeText::clean((string) $value)), null);
    }

    /**
     * Excel limits a cell to 32,767 UTF-16 code units; cut instead of
     * aborting a half-sent download.
     */
    private function fitCell(string $value): string
    {
        $utf16 = mb_convert_encoding($value, 'UTF-16LE', 'UTF-8');
        if (strlen($utf16) <= self::MAX_CELL_LENGTH * 2) {
            return $value;
        }

        // mb_strcut never splits a surrogate pair.
        return mb_convert_encoding(mb_strcut($utf16, 0, self::MAX_CELL_LENGTH * 2, 'UTF-16LE'), 'UTF-8', 'UTF-16LE');
    }

    private function label(string $key): string
    {
        return trans('export.'.$key, [], $this->query->locale);
    }
}
