<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExcelExportStyler
{
    public static function styleTable(
        Worksheet $sheet,
        int $headerRow,
        int $columnCount,
        int $dataRowCount = 0,
        bool $freezePane = true
    ): void {
        if ($columnCount < 1 || $headerRow < 1) {
            return;
        }

        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $lastRow = max($headerRow, $headerRow + max(0, $dataRowCount));
        $headerRange = 'A'.$headerRow.':'.$lastColumn.$headerRow;
        $tableRange = 'A'.$headerRow.':'.$lastColumn.$lastRow;

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F2937'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle($tableRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'BFC3C8'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
            ],
        ]);

        if ($freezePane) {
            $sheet->freezePane('A'.($headerRow + 1));
        }

        for ($col = 1; $col <= $columnCount; $col++) {
            $columnLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }
    }

    /**
     * @param  array<int, int>  $columns
     */
    public static function formatNumberColumns(
        Worksheet $sheet,
        int $startRow,
        int $endRow,
        array $columns,
        string $format = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1
    ): void {
        if ($startRow < 1 || $endRow < $startRow || $columns === []) {
            return;
        }

        foreach ($columns as $columnIndex) {
            if ($columnIndex < 1) {
                continue;
            }
            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
            $range = $columnLetter.$startRow.':'.$columnLetter.$endRow;
            $sheet->getStyle($range)->getNumberFormat()->setFormatCode($format);
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }
}

