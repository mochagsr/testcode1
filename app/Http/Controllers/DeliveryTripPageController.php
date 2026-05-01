<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DeliveryTrip;
use App\Services\AccountingService;
use App\Services\AuditLogService;
use App\Support\AppSetting;
use App\Support\ExcelExportStyler;
use App\Support\PrintTextFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SanderMuller\FluentValidation\FluentRule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryTripPageController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly AccountingService $accountingService
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));
        $tripDate = trim((string) $request->string('trip_date', ''));
        $selectedTripDate = $tripDate !== '' ? $tripDate : null;

        $trips = DeliveryTrip::query()
            ->onlyListColumns()
            ->with('creator:id,name')
            ->searchKeyword($search)
            ->when($selectedTripDate !== null, function ($query) use ($selectedTripDate): void {
                $query->whereDate('trip_date', $selectedTripDate);
            })
            ->orderByDesc('trip_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('delivery_trips.index', [
            'trips' => $trips,
            'search' => $search,
            'selectedTripDate' => $selectedTripDate,
        ]);
    }

    public function create(): View
    {
        return view('delivery_trips.create', [
            'prefillDate' => now()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $trip = DB::transaction(function () use ($data, $request): DeliveryTrip {
            $tripDate = Carbon::parse((string) $data['trip_date']);
            $totalCost = $this->totalCostFromData($data);

            $trip = DeliveryTrip::query()->create([
                'trip_number' => $this->generateTripNumber($tripDate->toDateString()),
                'trip_date' => $tripDate->toDateString(),
                'driver_name' => trim((string) $data['driver_name']),
                'assistant_name' => $this->nullIfEmpty((string) ($data['assistant_name'] ?? '')),
                'vehicle_plate' => $this->nullIfEmpty((string) ($data['vehicle_plate'] ?? '')),
                'member_count' => 0,
                'fuel_cost' => (int) $data['fuel_cost'],
                'toll_cost' => (int) $data['toll_cost'],
                'meal_cost' => (int) ($data['meal_cost'] ?? 0),
                'other_cost' => (int) ($data['other_cost'] ?? 0),
                'total_cost' => $totalCost,
                'notes' => $this->nullIfEmpty((string) ($data['notes'] ?? '')),
                'created_by_user_id' => (int) ($request->user()?->id ?? 0) ?: null,
            ]);

            $this->accountingService->postDeliveryTripExpense(
                tripId: (int) $trip->id,
                date: $tripDate,
                amount: $totalCost
            );

            $this->auditLogService->log(
                'delivery.trip.create',
                $trip,
                __('delivery_trip.audit_created', ['number' => $trip->trip_number]),
                $request,
                null,
                [
                    'trip_number' => $trip->trip_number,
                    'total_cost' => $trip->total_cost,
                    'assistant_name' => $trip->assistant_name,
                ]
            );

            return $trip;
        });

        return redirect()
            ->route('delivery-trips.show', $trip)
            ->with('success', __('delivery_trip.created_success', ['number' => $trip->trip_number]));
    }

    public function show(DeliveryTrip $deliveryTrip): View
    {
        $deliveryTrip->load([
            'creator:id,name',
            'updater:id,name',
        ]);

        return view('delivery_trips.show', [
            'trip' => $deliveryTrip,
        ]);
    }

    public function edit(DeliveryTrip $deliveryTrip): View
    {
        return view('delivery_trips.edit', [
            'trip' => $deliveryTrip,
        ]);
    }

    public function update(Request $request, DeliveryTrip $deliveryTrip): RedirectResponse
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($data, $request, $deliveryTrip): void {
            $trip = DeliveryTrip::query()
                ->whereKey($deliveryTrip->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = [
                'trip_date' => optional($trip->trip_date)->format('Y-m-d'),
                'driver_name' => $trip->driver_name,
                'assistant_name' => $trip->assistant_name,
                'vehicle_plate' => $trip->vehicle_plate,
                'fuel_cost' => (int) $trip->fuel_cost,
                'toll_cost' => (int) $trip->toll_cost,
                'meal_cost' => (int) $trip->meal_cost,
                'other_cost' => (int) $trip->other_cost,
                'total_cost' => (int) $trip->total_cost,
            ];
            $beforeTotalCost = (int) $trip->total_cost;

            $tripDate = Carbon::parse((string) $data['trip_date']);
            $totalCost = $this->totalCostFromData($data);

            $trip->update([
                'trip_date' => $tripDate->toDateString(),
                'driver_name' => trim((string) $data['driver_name']),
                'assistant_name' => $this->nullIfEmpty((string) ($data['assistant_name'] ?? '')),
                'vehicle_plate' => $this->nullIfEmpty((string) ($data['vehicle_plate'] ?? '')),
                'member_count' => 0,
                'fuel_cost' => (int) $data['fuel_cost'],
                'toll_cost' => (int) $data['toll_cost'],
                'meal_cost' => (int) ($data['meal_cost'] ?? 0),
                'other_cost' => (int) ($data['other_cost'] ?? 0),
                'total_cost' => $totalCost,
                'notes' => $this->nullIfEmpty((string) ($data['notes'] ?? '')),
                'updated_by_user_id' => (int) ($request->user()?->id ?? 0) ?: null,
            ]);
            $trip->members()->delete();

            $this->accountingService->postDeliveryTripAdjustment(
                tripId: (int) $trip->id,
                date: $tripDate,
                difference: $totalCost - $beforeTotalCost
            );

            $this->auditLogService->log(
                'delivery.trip.update',
                $trip,
                __('delivery_trip.audit_updated', ['number' => $trip->trip_number]),
                $request,
                $before,
                [
                    'trip_date' => $trip->trip_date?->format('Y-m-d'),
                    'driver_name' => $trip->driver_name,
                    'assistant_name' => $trip->assistant_name,
                    'vehicle_plate' => $trip->vehicle_plate,
                    'fuel_cost' => (int) $trip->fuel_cost,
                    'toll_cost' => (int) $trip->toll_cost,
                    'meal_cost' => (int) $trip->meal_cost,
                    'other_cost' => (int) $trip->other_cost,
                    'total_cost' => (int) $trip->total_cost,
                ]
            );
        });

        return redirect()
            ->route('delivery-trips.show', $deliveryTrip)
            ->with('success', __('delivery_trip.updated_success'));
    }

    public function print(DeliveryTrip $deliveryTrip): View
    {
        $deliveryTrip->load([
            'creator:id,name',
            'updater:id,name',
        ]);

        return view('delivery_trips.print', [
            'trip' => $deliveryTrip,
        ]);
    }

    public function exportPdf(DeliveryTrip $deliveryTrip)
    {
        $deliveryTrip->load([
            'creator:id,name',
            'updater:id,name',
        ]);

        $filename = strtolower((string) $deliveryTrip->trip_number).'.pdf';

        return Pdf::loadView('delivery_trips.print', [
            'trip' => $deliveryTrip,
            'isPdf' => true,
        ])->setPaper(\App\Support\PrintPaperSize::continuousForm95x11())->download($filename);
    }

    public function exportExcel(DeliveryTrip $deliveryTrip): StreamedResponse
    {
        $deliveryTrip->load([
            'creator:id,name',
            'updater:id,name',
        ]);

        $filename = strtolower((string) $deliveryTrip->trip_number).'.xlsx';

        return response()->streamDownload(function () use ($deliveryTrip): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Perjalanan Kirim');
            $settings = AppSetting::getValues([
                'company_name' => 'CV. PUSTAKA GRAFIKA',
                'company_address' => '',
                'company_phone' => '',
                'company_email' => '',
                'company_notes' => '',
            ]);
            $companyAddress = PrintTextFormatter::wrapWords(trim((string) ($settings['company_address'] ?? '')), 5);
            $companyDetail = collect([
                $companyAddress,
                trim((string) ($settings['company_phone'] ?? '')),
                trim((string) ($settings['company_email'] ?? '')),
                trim((string) ($settings['company_notes'] ?? '')),
            ])->filter(fn (string $line): bool => $line !== '')->implode("\n");
            $notes = PrintTextFormatter::wrapWords(trim((string) ($deliveryTrip->notes ?? '')), 4);

            $sheet->mergeCells('A1:F1');
            $sheet->setCellValue('A1', __('delivery_trip.title'));
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->mergeCells('A2:B2');
            $sheet->setCellValue('A2', trim((string) ($settings['company_name'] ?? 'CV. PUSTAKA GRAFIKA')));
            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13);
            $sheet->mergeCells('A3:B5');
            $sheet->setCellValue('A3', $companyDetail !== '' ? $companyDetail : '-');
            $sheet->getStyle('A3')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

            $sheet->mergeCells('C2:D2');
            $sheet->setCellValue('C2', $deliveryTrip->trip_number);
            $sheet->getStyle('C2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C2')->getFont()->setBold(true);

            $metaRows = [
                [__('txn.date'), optional($deliveryTrip->trip_date)->format('d-m-Y')],
                [__('delivery_trip.driver_name'), $deliveryTrip->driver_name],
                [__('delivery_trip.assistant_name'), $deliveryTrip->assistant_name ?: '-'],
                [__('delivery_trip.vehicle_plate'), $deliveryTrip->vehicle_plate ?: '-'],
            ];
            $metaRow = 2;
            foreach ($metaRows as [$label, $value]) {
                $sheet->setCellValue('E'.$metaRow, $label);
                $sheet->setCellValue('F'.$metaRow, $value);
                $metaRow++;
            }
            $sheet->getStyle('E2:E5')->getFont()->setBold(true);

            $costHeaderRow = 7;
            $sheet->fromArray([[__('delivery_trip.cost_breakdown'), __('txn.amount')]], null, 'A'.$costHeaderRow);
            $costRows = [
                [__('delivery_trip.fuel_cost'), (int) $deliveryTrip->fuel_cost],
                [__('delivery_trip.toll_cost'), (int) $deliveryTrip->toll_cost],
                [__('delivery_trip.meal_cost'), (int) $deliveryTrip->meal_cost],
                [__('delivery_trip.other_cost'), (int) $deliveryTrip->other_cost],
                [__('delivery_trip.total_cost'), (int) $deliveryTrip->total_cost],
            ];
            $sheet->fromArray($costRows, null, 'A'.($costHeaderRow + 1));
            ExcelExportStyler::styleTable($sheet, $costHeaderRow, 2, count($costRows), true);
            ExcelExportStyler::formatNumberColumns($sheet, $costHeaderRow + 1, $costHeaderRow + count($costRows), [2], '#,##0');

            $notesHeaderRow = 7;
            $sheet->mergeCells('D'.$notesHeaderRow.':F'.$notesHeaderRow);
            $sheet->setCellValue('D'.$notesHeaderRow, __('txn.notes'));
            $sheet->getStyle('D'.$notesHeaderRow.':F'.$notesHeaderRow)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->mergeCells('D8:F12');
            $sheet->setCellValue('D8', $notes !== '' ? $notes : '-');
            $sheet->getStyle('D8:F12')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle('D8:F12')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

            $signatureRow = 15;
            $sheet->mergeCells('A'.$signatureRow.':B'.$signatureRow);
            $sheet->mergeCells('E'.$signatureRow.':F'.$signatureRow);
            $sheet->setCellValue('A'.$signatureRow, __('delivery_trip.signature_driver'));
            $sheet->setCellValue('E'.$signatureRow, __('delivery_trip.signature_admin'));
            $sheet->getStyle('A'.$signatureRow.':F'.$signatureRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->mergeCells('A'.($signatureRow + 2).':B'.($signatureRow + 2));
            $sheet->mergeCells('E'.($signatureRow + 2).':F'.($signatureRow + 2));
            $sheet->setCellValue('A'.($signatureRow + 2), $deliveryTrip->driver_name);
            $sheet->setCellValue('E'.($signatureRow + 2), $deliveryTrip->creator?->name ?: '-');
            $sheet->getStyle('A'.($signatureRow + 2).':F'.($signatureRow + 2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A'.($signatureRow + 1).':B'.($signatureRow + 1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle('E'.($signatureRow + 1).':F'.($signatureRow + 1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            foreach (range('A', 'F') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'trip_date' => FluentRule::date()->required(),
            'driver_name' => FluentRule::string()->required()->max(120),
            'assistant_name' => FluentRule::string()->nullable()->max(120),
            'vehicle_plate' => FluentRule::string()->nullable()->max(40),
            'fuel_cost' => FluentRule::integer()->nullable()->min(0),
            'toll_cost' => FluentRule::integer()->nullable()->min(0),
            'meal_cost' => FluentRule::integer()->nullable()->min(0),
            'other_cost' => FluentRule::integer()->nullable()->min(0),
            'notes' => FluentRule::string()->nullable(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function totalCostFromData(array $data): int
    {
        return (int) ($data['fuel_cost'] ?? 0)
            + (int) ($data['toll_cost'] ?? 0)
            + (int) ($data['meal_cost'] ?? 0)
            + (int) ($data['other_cost'] ?? 0);
    }

    private function nullIfEmpty(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function generateTripNumber(string $tripDate): string
    {
        $prefix = 'TRP-'.date('dmY', strtotime($tripDate));

        $latest = DeliveryTrip::query()
            ->whereDate('trip_date', $tripDate)
            ->where('trip_number', 'like', $prefix.'-%')
            ->lockForUpdate()
            ->max('trip_number');

        $next = 1;
        if (is_string($latest) && $latest !== '') {
            $next = ((int) substr($latest, -4)) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }
}
