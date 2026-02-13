<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Support\AppCache;
use App\Support\ExcelExportStyler;
use App\Support\SemesterBookService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryNotePageController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request): View
    {
        $isAdminUser = (string) ($request->user()?->role ?? '') === 'admin';
        $search = trim((string) $request->string('search', ''));
        $semester = trim((string) $request->string('semester', ''));
        $status = trim((string) $request->string('status', ''));
        $noteDate = trim((string) $request->string('note_date', ''));
        $selectedStatus = in_array($status, ['active', 'canceled'], true) ? $status : null;
        $selectedSemester = $semester !== '' ? $semester : null;
        $selectedNoteDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $noteDate) === 1 ? $noteDate : null;
        $selectedNoteDateRange = $selectedNoteDate !== null
            ? [
                Carbon::parse($selectedNoteDate)->startOfDay(),
                Carbon::parse($selectedNoteDate)->endOfDay(),
            ]
            : null;
        $isDefaultRecentMode = $selectedNoteDateRange === null && $selectedSemester === null && $search === '';
        $recentRangeStart = now()->subDays(6)->startOfDay();
        $todayRange = [now()->startOfDay(), now()->endOfDay()];

        $currentSemester = $this->currentSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);
        $semesterRange = $this->semesterDateRange($selectedSemester);

        $semesterOptions = Cache::remember(
            AppCache::lookupCacheKey('delivery_notes.index.semester_options.base'),
            now()->addSeconds(60),
            fn () => $this->semesterBookService()->buildSemesterOptionCollection(
                DeliveryNote::query()
                    ->whereNotNull('note_date')
                    ->orderByDesc('note_date')
                    ->pluck('note_date')
                    ->map(fn ($date): string => $this->semesterPeriodFromDate($date))
                    ->merge($this->semesterBookService()->configuredSemesterOptions()),
                false,
                true
            )
        );
        $semesterOptions = $isAdminUser
            ? $semesterOptions->values()
            : $this->semesterBookService()->buildSemesterOptionCollection($semesterOptions->all(), true, false);
        if ($selectedSemester !== null && ! $semesterOptions->contains($selectedSemester)) {
            $selectedSemester = null;
            $semesterRange = null;
        }

        $notes = DeliveryNote::query()
            ->select([
                'id',
                'note_number',
                'note_date',
                'customer_id',
                'recipient_name',
                'city',
                'created_by_name',
                'is_canceled',
            ])
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
            ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                $query->where('is_canceled', $selectedStatus === 'canceled');
            })
            ->when($selectedNoteDateRange !== null, function ($query) use ($selectedNoteDateRange): void {
                $query->whereBetween('note_date', $selectedNoteDateRange);
            })
            ->when($isDefaultRecentMode, function ($query) use ($recentRangeStart): void {
                $query->where('note_date', '>=', $recentRangeStart);
            })
            ->latest('note_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $todaySummary = Cache::remember(
            AppCache::lookupCacheKey('delivery_notes.index.today_summary', [
                'status' => $selectedStatus ?? 'all',
                'date' => now()->toDateString(),
            ]),
            now()->addSeconds(30),
            function () use ($todayRange, $selectedStatus) {
                return (object) [
                    'total_notes' => (int) DeliveryNote::query()
                        ->whereBetween('note_date', $todayRange)
                        ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                            $query->where('is_canceled', $selectedStatus === 'canceled');
                        })
                        ->count(),
                    'total_qty' => (int) DeliveryNoteItem::query()
                        ->join('delivery_notes', 'delivery_note_items.delivery_note_id', '=', 'delivery_notes.id')
                        ->whereBetween('delivery_notes.note_date', $todayRange)
                        ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                            $query->where('delivery_notes.is_canceled', $selectedStatus === 'canceled');
                        })
                        ->sum('delivery_note_items.quantity'),
                ];
            }
        );

        return view('delivery_notes.index', [
            'notes' => $notes,
            'search' => $search,
            'semesterOptions' => $semesterOptions,
            'selectedSemester' => $selectedSemester,
            'selectedStatus' => $selectedStatus,
            'selectedNoteDate' => $selectedNoteDate,
            'isDefaultRecentMode' => $isDefaultRecentMode,
            'currentSemester' => $currentSemester,
            'previousSemester' => $previousSemester,
            'todaySummary' => $todaySummary,
        ]);
    }

    public function create(): View
    {
        $oldCustomerId = (int) old('customer_id', 0);
        $customers = Cache::remember(
            AppCache::lookupCacheKey('forms.delivery_notes.customers', ['limit' => 20]),
            now()->addSeconds(60),
            fn () => Customer::query()
                ->select(['id', 'name', 'city', 'phone', 'address'])
                ->orderBy('name')
                ->limit(20)
                ->get()
        );
        if ($oldCustomerId > 0 && ! $customers->contains('id', $oldCustomerId)) {
            $oldCustomer = Customer::query()
                ->select(['id', 'name', 'city', 'phone', 'address'])
                ->whereKey($oldCustomerId)
                ->first();
            if ($oldCustomer !== null) {
                $customers->prepend($oldCustomer);
            }
        }
        $customers = $customers->unique('id')->values();

        $oldProductIds = collect(old('items', []))
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();
        $products = Cache::remember(
            AppCache::lookupCacheKey('forms.delivery_notes.products', ['limit' => 20, 'active_only' => 1]),
            now()->addSeconds(60),
            fn () => Product::query()
                ->select(['id', 'code', 'name', 'unit', 'price_general'])
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(20)
                ->get()
        );
        if ($oldProductIds->isNotEmpty()) {
            $oldProducts = Product::query()
                ->select(['id', 'code', 'name', 'unit', 'price_general'])
                ->whereIn('id', $oldProductIds->all())
                ->get();
            $products = $oldProducts->concat($products)->unique('id')->values();
        }

        return view('delivery_notes.create', [
            'customers' => $customers,
            'products' => $products,
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
                'created_by_name' => auth()->user()?->name ?? __('txn.system_user'),
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

        $this->auditLogService->log(
            'delivery.note.create',
            $note,
            __('txn.audit_delivery_created', ['number' => $note->note_number]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $note->note_date]);

        return redirect()
            ->route('delivery-notes.show', $note)
            ->with('success', __('txn.delivery_note_created_success', ['number' => $note->note_number]));
    }

    public function show(DeliveryNote $deliveryNote): View
    {
        $deliveryNote->load(['customer:id,name,city,phone,address', 'items']);
        $itemProductIds = $deliveryNote->items
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();
        $products = Cache::remember(
            AppCache::lookupCacheKey('forms.delivery_notes.products', ['limit' => 20, 'active_only' => 1]),
            now()->addSeconds(60),
            fn () => Product::query()
                ->select(['id', 'code', 'name', 'unit', 'price_general'])
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(20)
                ->get()
        );
        if ($itemProductIds->isNotEmpty()) {
            $itemProducts = Product::query()
                ->select(['id', 'code', 'name', 'unit', 'price_general'])
                ->whereIn('id', $itemProductIds->all())
                ->get();
            $products = $itemProducts->concat($products)->unique('id')->values();
        }

        return view('delivery_notes.show', [
            'note' => $deliveryNote,
            'products' => $products,
        ]);
    }

    public function adminUpdate(Request $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        $data = $request->validate([
            'note_date' => ['required', 'date'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'recipient_phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_name' => ['required', 'string', 'max:200'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($deliveryNote, $data): void {
            $note = DeliveryNote::query()
                ->with('items')
                ->whereKey($deliveryNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            $note->update([
                'note_date' => $data['note_date'],
                'recipient_name' => $data['recipient_name'],
                'recipient_phone' => $data['recipient_phone'] ?? null,
                'city' => $data['city'] ?? null,
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $note->items()->delete();

            foreach ($data['items'] as $row) {
                $productId = $row['product_id'] ?? null;
                $productCode = null;
                $productName = $row['product_name'];
                $unit = $row['unit'] ?? null;
                $unitPrice = $row['unit_price'] ?? null;

                if ($productId) {
                    $product = Product::query()->find($productId);
                    if ($product) {
                        $productCode = $product->code;
                        $productName = $product->name;
                        $unit = $unit ?: $product->unit;
                        $unitPrice = $unitPrice !== null && $unitPrice !== '' ? $unitPrice : $product->price_general;
                    }
                }

                DeliveryNoteItem::create([
                    'delivery_note_id' => $note->id,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                    'product_name' => $productName,
                    'unit' => $unit,
                    'quantity' => $row['quantity'],
                    'unit_price' => $unitPrice !== null && $unitPrice !== '' ? (float) round((float) $unitPrice) : null,
                    'notes' => $row['notes'] ?? null,
                ]);
            }
        });

        $deliveryNote->refresh();
        $this->auditLogService->log(
            'delivery.note.admin_update',
            $deliveryNote,
            __('txn.audit_delivery_admin_updated', ['number' => $deliveryNote->note_number]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $deliveryNote->note_date]);

        return redirect()
            ->route('delivery-notes.show', $deliveryNote)
            ->with('success', __('txn.admin_update_saved'));
    }

    public function cancel(Request $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:1000'],
        ]);

        $deliveryNote->update([
            'is_canceled' => true,
            'canceled_at' => now(),
            'canceled_by_user_id' => auth()->id(),
            'cancel_reason' => $data['cancel_reason'],
        ]);

        $this->auditLogService->log(
            'delivery.note.cancel',
            $deliveryNote,
            __('txn.audit_delivery_canceled', ['number' => $deliveryNote->note_number]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $deliveryNote->note_date]);

        return redirect()
            ->route('delivery-notes.show', $deliveryNote)
            ->with('success', __('txn.transaction_canceled_success'));
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
        $filename = $deliveryNote->note_number.'.xlsx';

        return response()->streamDownload(function () use ($deliveryNote): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Surat Jalan');
            $rows = [];
            $rows[] = [__('txn.delivery_notes_title').' '.__('txn.note_number'), $deliveryNote->note_number];
            $rows[] = [__('txn.date'), $deliveryNote->note_date?->format('d-m-Y')];
            $rows[] = [__('txn.recipient'), $deliveryNote->recipient_name];
            $rows[] = [__('txn.phone'), $deliveryNote->recipient_phone];
            $rows[] = [__('txn.city'), $deliveryNote->city];
            $rows[] = [__('txn.address'), $deliveryNote->address];
            $rows[] = [__('txn.created_by'), $deliveryNote->created_by_name];
            $rows[] = [__('txn.notes'), $deliveryNote->notes];
            $rows[] = [];
            $rows[] = [__('txn.items')];
            $rows[] = [__('txn.name'), __('txn.unit'), __('txn.qty'), __('txn.price'), __('txn.notes')];

            foreach ($deliveryNote->items as $item) {
                $rows[] = [
                    $item->product_name,
                    $item->unit,
                    $item->quantity,
                    $item->unit_price !== null ? number_format((int) round((float) $item->unit_price), 0, ',', '.') : null,
                    $item->notes,
                ];
            }

            $sheet->fromArray($rows, null, 'A1');
            $itemsCount = $deliveryNote->items->count();
            $itemsHeaderRow = 11;
            ExcelExportStyler::styleTable($sheet, $itemsHeaderRow, 5, $itemsCount, true);
            ExcelExportStyler::formatNumberColumns($sheet, $itemsHeaderRow + 1, $itemsHeaderRow + $itemsCount, [3, 4], '#,##0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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
        return $this->semesterBookService()->currentSemester();
    }

    private function previousSemesterPeriod(string $period): string
    {
        return $this->semesterBookService()->previousSemester($period);
    }

    private function semesterDateRange(?string $period): ?array
    {
        if ($period === null) {
            return null;
        }
        $normalized = $this->semesterBookService()->normalizeSemester($period);
        if ($normalized === null) {
            return null;
        }
        if (preg_match('/^S([12])-(\d{2})(\d{2})$/', $normalized, $matches) === 1) {
            $half = (int) $matches[1];
            $startYear = 2000 + (int) $matches[2];
            $endYear = 2000 + (int) $matches[3];
            if ($half === 1) {
                return [
                    'start' => Carbon::create($startYear, 5, 1)->startOfDay()->toDateString(),
                    'end' => Carbon::create($startYear, 10, 31)->endOfDay()->toDateString(),
                ];
            }

            return [
                'start' => Carbon::create($startYear, 11, 1)->startOfDay()->toDateString(),
                'end' => Carbon::create($endYear, 4, 30)->endOfDay()->toDateString(),
            ];
        }
        return null;
    }

    private function semesterPeriodFromDate(Carbon|string|null $date): string
    {
        $rawDate = $date instanceof Carbon ? $date->format('Y-m-d') : (string) $date;
        return $this->semesterBookService()->semesterFromDate($rawDate) ?? $this->currentSemesterPeriod();
    }

    private function semesterBookService(): SemesterBookService
    {
        return app(SemesterBookService::class);
    }
}
