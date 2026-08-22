<?php

namespace App\Traits;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait HasExport
{
    /**
     * Stream an Excel (.xlsx) export built from $headers + $rows.
     *
     * @param  string      $filename  Without extension.
     * @param  array       $headers   Column headings, in order.
     * @param  Collection  $rows      Each entry: array or object with toArray(), same key order as $headers.
     */
    protected function exportExcel(string $filename, array $headers, Collection $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);

        $rowNumber = 2;
        foreach ($rows as $row) {
            $values = $this->normalizeRow($row);
            $sheet->fromArray($values, null, 'A' . $rowNumber);
            $rowNumber++;
        }

        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.xlsx"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Stream a CSV export built from $headers + $rows. No external dependency.
     */
    protected function exportCsv(string $filename, array $headers, Collection $rows): StreamedResponse
    {
        return new StreamedResponse(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $this->normalizeRow($row));
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Stream a Word (.docx) export with a title and a simple table built from $rows.
     * The first row's keys (or array indices) are used as the table header.
     */
    protected function exportWord(string $filename, string $title, Collection $rows): StreamedResponse
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addTitle($title, 1);

        $table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 80]);

        $first = $this->normalizeRow($rows->first() ?? []);
        $headers = array_keys($first);

        if (!empty($headers)) {
            $table->addRow();
            foreach ($headers as $header) {
                $table->addCell(2000)->addText((string) $header, ['bold' => true]);
            }
        }

        foreach ($rows as $row) {
            $values = $this->normalizeRow($row);
            $table->addRow();
            foreach ($values as $value) {
                $table->addCell(2000)->addText((string) $value);
            }
        }

        $writer = WordIOFactory::createWriter($phpWord, 'Word2007');

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.docx"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Normalize a row entry (array or object with toArray()) into a plain array of scalars.
     */
    private function normalizeRow(mixed $row): array
    {
        if (is_array($row)) {
            $values = $row;
        } elseif (is_object($row) && method_exists($row, 'toArray')) {
            $values = $row->toArray();
        } else {
            $values = (array) $row;
        }

        return array_map(
            fn($v) => is_scalar($v) || $v === null ? $v : (string) $v,
            $values
        );
    }
}
