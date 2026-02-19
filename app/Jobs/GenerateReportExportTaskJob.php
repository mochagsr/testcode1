<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\ReportExportController;
use App\Models\ReportExportTask;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateReportExportTaskJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public function __construct(
        public readonly int $taskId
    ) {}

    public function handle(ReportExportController $reportController): void
    {
        $task = ReportExportTask::query()->find($this->taskId);
        if ($task === null) {
            return;
        }
        if ((string) $task->status === 'canceled') {
            return;
        }

        $task->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            $filters = (array) ($task->filters ?? []);
            $report = $reportController->reportData(
                (string) $task->dataset,
                $this->toNullableString($filters['semester'] ?? null),
                $this->toNullableInt($filters['customer_id'] ?? null),
                $this->toNullableString($filters['user_role'] ?? null),
                isset($filters['finance_lock']) && $filters['finance_lock'] !== '' ? (int) $filters['finance_lock'] : null,
                $this->toIntArray($filters['customer_ids'] ?? []),
                $this->toNullableInt($filters['outgoing_supplier_id'] ?? null),
                $this->toNullableString($filters['transaction_type'] ?? null),
            );
            $printedAt = now('Asia/Jakarta');

            if ($task->format === 'pdf') {
                $binary = Pdf::loadView('reports.print', [
                    'title' => $report['title'],
                    'headers' => $report['headers'],
                    'rows' => $report['rows'],
                    'summary' => $report['summary'],
                    'filters' => $report['filters'],
                    'layout' => $report['layout'] ?? null,
                    'receivableSemesterHeaders' => $report['receivable_semester_headers'] ?? [],
                    'printedAt' => $printedAt,
                    'isPdf' => true,
                ])->setPaper('a4', 'landscape')->output();
                $extension = 'pdf';
            } else {
                $binary = $this->buildExcelBinary($report, $printedAt);
                $extension = 'xlsx';
            }

            $fileName = $task->dataset.'-'.$printedAt->format('Ymd-His').'.'.$extension;
            $filePath = 'private/report_exports/'.(int) $task->user_id.'/'.(int) $task->id.'/'.$fileName;
            $task->refresh();
            if ((string) $task->status === 'canceled') {
                return;
            }
            Storage::disk('local')->put($filePath, $binary);

            $task->update([
                'status' => 'ready',
                'file_name' => $fileName,
                'file_path' => $filePath,
                'generated_at' => now(),
            ]);
        } catch (\Throwable $throwable) {
            $task->update([
                'status' => 'failed',
                'error_message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $report
     */
    private function buildExcelBinary(array $report, Carbon $printedAt): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');
        $phoneHeaderKey = strtolower(__('report.columns.phone'));
        $rowCursor = 1;
        $sheet->setCellValue('A'.$rowCursor, (string) ($report['title'] ?? ''));
        $rowCursor++;
        $sheet->setCellValue('A'.$rowCursor, __('report.printed'));
        $sheet->setCellValue('B'.$rowCursor, $printedAt->format('d-m-Y H:i:s').' WIB');
        $rowCursor++;

        if (! empty($report['filters'])) {
            foreach ((array) $report['filters'] as $filter) {
                $sheet->setCellValue('A'.$rowCursor, (string) ($filter['label'] ?? ''));
                $sheet->setCellValue('B'.$rowCursor, (string) ($filter['value'] ?? ''));
                $rowCursor++;
            }
        }

        if (! empty($report['summary'])) {
            foreach ((array) $report['summary'] as $item) {
                $value = (($item['type'] ?? 'number') === 'currency')
                    ? 'Rp '.number_format((int) round((float) ($item['value'] ?? 0)), 0, ',', '.')
                    : (int) round((float) ($item['value'] ?? 0));
                $sheet->setCellValue('A'.$rowCursor, (string) ($item['label'] ?? ''));
                $sheet->setCellValue('B'.$rowCursor, (string) $value);
                $rowCursor++;
            }
        }

        $rowCursor++;
        $headers = (array) ($report['headers'] ?? []);
        $rows = (array) ($report['rows'] ?? []);
        $sheet->fromArray([$headers], null, 'A'.$rowCursor);
        $headerRowIndex = $rowCursor;
        $rowCursor++;

        foreach ($rows as $row) {
            $formatted = [];
            foreach ($headers as $index => $header) {
                $value = $row[$index] ?? null;
                $text = $value === null ? '' : (string) $value;
                if (strtolower(trim((string) $header)) === $phoneHeaderKey && $text !== '') {
                    $text = "'".$text;
                }
                $formatted[] = $text;
            }
            $sheet->fromArray([$formatted], null, 'A'.$rowCursor);
            $rowCursor++;
        }

        $columnCount = count($headers);
        if ($columnCount > 0) {
            $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);
            $lastDataRow = max($headerRowIndex, $rowCursor - 1);
            $sheet->getStyle('A'.$headerRowIndex.':'.$lastColumn.$headerRowIndex)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle('A'.$headerRowIndex.':'.$lastColumn.$lastDataRow)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFC3C8']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
            ]);
            for ($col = 1; $col <= $columnCount; $col++) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
            }
            $sheet->freezePane('A'.($headerRowIndex + 1));
        }

        ob_start();
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        $binary = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $binary;
    }

    private function toNullableInt(mixed $value): ?int
    {
        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : null;
    }

    private function toNullableString(mixed $value): ?string
    {
        $stringValue = trim((string) $value);
        return $stringValue !== '' ? $stringValue : null;
    }

    /**
     * @param array<int, mixed>|mixed $value
     * @return array<int, int>
     */
    private function toIntArray(mixed $value): array
    {
        $items = is_array($value) ? $value : [];
        return collect($items)
            ->map(fn ($item): int => (int) $item)
            ->filter(fn (int $item): bool => $item > 0)
            ->values()
            ->all();
    }
}
