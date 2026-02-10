<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryNotePageController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));
        $semester = trim((string) $request->string('semester', ''));
        $selectedSemester = $semester !== '' ? $semester : null;

        $currentSemester = $this->currentSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);
        $semesterRange = $this->semesterDateRange($selectedSemester);

        $semesterOptions = DeliveryNote::query()
            ->whereNotNull('note_date')
            ->orderByDesc('note_date')
            ->pluck('note_date')
            ->map(fn ($date): string => $this->semesterPeriodFromDate($date))
            ->merge($this->configuredSemesterOptions())
            ->push($currentSemester)
            ->push($previousSemester)
            ->unique()
            ->sortDesc()
            ->values();

        $notes = DeliveryNote::query()
            ->with('customer:id,name,city')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('note_number', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->when($semesterRange !== null, function ($query) use ($semesterRange): void {
                $query->whereBetween('note_date', [$semesterRange['start'], $semesterRange['end']]);
            })
            ->latest('note_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $summaryQuery = DeliveryNote::query()
            ->when($semesterRange !== null, function ($query) use ($semesterRange): void {
                $query->whereBetween('note_date', [$semesterRange['start'], $semesterRange['end']]);
            });

        $summary = (object) [
            'total_notes' => (int) $summaryQuery->count(),
            'total_qty' => (int) DeliveryNoteItem::query()
                ->join('delivery_notes', 'delivery_note_items.delivery_note_id', '=', 'delivery_notes.id')
                ->when($semesterRange !== null, function ($query) use ($semesterRange): void {
                    $query->whereBetween('delivery_notes.note_date', [$semesterRange['start'], $semesterRange['end']]);
                })
                ->sum('delivery_note_items.quantity'),
        ];

        return view('delivery_notes.index', [
            'notes' => $notes,
            'search' => $search,
            'semesterOptions' => $semesterOptions,
            'selectedSemester' => $selectedSemester,
            'currentSemester' => $currentSemester,
            'previousSemester' => $previousSemester,
            'summary' => $summary,
        ]);
    }

    public function create(): View
    {
        return view('delivery_notes.create', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'city', 'phone', 'address']),
            'products' => Product::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'unit', 'price_general']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'note_date' => ['required', 'date'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'recipient_phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'created_by_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_code' => ['nullable', 'string', 'max:60'],
            'items.*.product_name' => ['required', 'string', 'max:200'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $note = DB::transaction(function () use ($data): DeliveryNote {
            $noteDate = $data['note_date'];
            $noteNumber = $this->generateNoteNumber($noteDate);

            $note = DeliveryNote::create([
                'note_number' => $noteNumber,
                'note_date' => $noteDate,
                'customer_id' => $data['customer_id'] ?? null,
                'recipient_name' => $data['recipient_name'],
                'recipient_phone' => $data['recipient_phone'] ?? null,
                'city' => $data['city'] ?? null,
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_name' => $data['created_by_name'] ?? 'System',
            ]);

            foreach ($data['items'] as $row) {
                $productId = $row['product_id'] ?? null;
                $productCode = $row['product_code'] ?? null;
                $productName = $row['product_name'];
                $unit = $row['unit'] ?? null;
                $unitPrice = $row['unit_price'] ?? null;

                if ($productId) {
                    $product = Product::query()->find($productId);
                    if ($product) {
                        $productCode = $productCode ?: $product->code;
                        $productName = $productName ?: $product->name;
                        $unit = $unit ?: $product->unit;
                    }
                }

                DeliveryNoteItem::create([
                    'delivery_note_id' => $note->id,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                    'product_name' => $productName,
                    'unit' => $unit,
                    'quantity' => $row['quantity'],
                    'unit_price' => $unitPrice !== null && $unitPrice !== '' ? $unitPrice : null,
                    'notes' => $row['notes'] ?? null,
                ]);
            }

            return $note;
        });

        return redirect()
            ->route('delivery-notes.show', $note)
            ->with('success', "Delivery note {$note->note_number} has been created.");
    }

    public function show(DeliveryNote $deliveryNote): View
    {
        $deliveryNote->load(['customer:id,name,city,phone,address', 'items']);

        return view('delivery_notes.show', [
            'note' => $deliveryNote,
        ]);
    }

    public function print(DeliveryNote $deliveryNote): View
    {
        $deliveryNote->load(['customer:id,name,city,phone,address', 'items']);

        return view('delivery_notes.print', [
            'note' => $deliveryNote,
        ]);
    }

    public function exportPdf(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load(['customer:id,name,city,phone,address', 'items']);

        $filename = $deliveryNote->note_number.'.pdf';
        $pdf = Pdf::loadView('delivery_notes.print', [
            'note' => $deliveryNote,
            'isPdf' => true,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    public function exportExcel(DeliveryNote $deliveryNote): StreamedResponse
    {
        $deliveryNote->load(['customer:id,name,city,phone,address', 'items']);
        $filename = $deliveryNote->note_number.'.csv';

        return response()->streamDownload(function () use ($deliveryNote): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Delivery Note Number', $deliveryNote->note_number]);
            fputcsv($handle, ['Date', $deliveryNote->note_date?->format('d-m-Y')]);
            fputcsv($handle, ['Recipient', $deliveryNote->recipient_name]);
            fputcsv($handle, ['Phone', $deliveryNote->recipient_phone]);
            fputcsv($handle, ['City', $deliveryNote->city]);
            fputcsv($handle, ['Address', $deliveryNote->address]);
            fputcsv($handle, ['Created By', $deliveryNote->created_by_name]);
            fputcsv($handle, ['Notes', $deliveryNote->notes]);
            fputcsv($handle, []);
            fputcsv($handle, ['Items']);
            fputcsv($handle, ['Code', 'Name', 'Unit', 'Qty', 'Unit Price', 'Notes']);

            foreach ($deliveryNote->items as $item) {
                fputcsv($handle, [
                    $item->product_code,
                    $item->product_name,
                    $item->unit,
                    $item->quantity,
                    $item->unit_price,
                    $item->notes,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function generateNoteNumber(string $date): string
    {
        $prefix = 'SJ-'.date('Ymd', strtotime($date));
        $count = DeliveryNote::query()
            ->whereDate('note_date', $date)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }

    private function currentSemesterPeriod(): string
    {
        $year = now()->year;
        $month = (int) now()->format('n');
        $semester = $month <= 6 ? 1 : 2;

        return "S{$semester}-{$year}";
    }

    private function previousSemesterPeriod(string $period): string
    {
        if (preg_match('/^S([12])-(\d{4})$/', $period, $matches) === 1) {
            $semester = (int) $matches[1];
            $year = (int) $matches[2];

            if ($semester === 2) {
                return "S1-{$year}";
            }

            return 'S2-'.($year - 1);
        }

        $previous = now()->subMonths(6);
        $semester = (int) $previous->format('n') <= 6 ? 1 : 2;

        return "S{$semester}-{$previous->year}";
    }

    private function semesterDateRange(?string $period): ?array
    {
        if ($period === null || preg_match('/^S([12])-(\d{4})$/', $period, $matches) !== 1) {
            return null;
        }

        $semester = (int) $matches[1];
        $year = (int) $matches[2];
        $start = Carbon::create($year, $semester === 1 ? 1 : 7, 1)->startOfDay();
        $end = (clone $start)->addMonths(6)->subDay()->endOfDay();

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ];
    }

    private function semesterPeriodFromDate(Carbon|string|null $date): string
    {
        $dateValue = $date instanceof Carbon ? $date : Carbon::parse((string) $date);
        $semester = (int) $dateValue->format('n') <= 6 ? 1 : 2;

        return "S{$semester}-{$dateValue->year}";
    }

    private function configuredSemesterOptions()
    {
        return collect(explode(',', (string) AppSetting::getValue('semester_period_options', '')))
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '');
    }
}
