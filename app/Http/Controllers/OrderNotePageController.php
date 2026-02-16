<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDateFilters;
use App\Http\Controllers\Concerns\ResolvesSemesterOptions;
use App\Models\Customer;
use App\Models\OrderNote;
use App\Models\OrderNoteItem;
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

class OrderNotePageController extends Controller
{
    use ResolvesDateFilters;
    use ResolvesSemesterOptions;

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SemesterBookService $semesterBookService
    ) {}

    public function index(Request $request): View
    {
        $now = now();
        $isAdminUser = (string) ($request->user()?->role ?? '') === 'admin';
        $search = trim((string) $request->string('search', ''));
        $semester = (string) $request->string('semester', '');
        $status = trim((string) $request->string('status', ''));
        $noteDate = trim((string) $request->string('note_date', ''));
        $selectedStatus = in_array($status, ['active', 'canceled'], true) ? $status : null;
        $selectedSemester = $this->normalizedSemesterInput($semester);
        $selectedNoteDate = $this->selectedDateFilter($noteDate);
        $selectedNoteDateRange = $this->selectedDateRange($selectedNoteDate);
        $isDefaultRecentMode = $selectedNoteDateRange === null && $selectedSemester === null && $search === '';
        $recentRangeStart = $now->copy()->subDays(6)->startOfDay();
        $todayRange = [$now->copy()->startOfDay(), $now->copy()->endOfDay()];

        $currentSemester = $this->currentSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);
        $semesterRange = $this->semesterDateRange($selectedSemester);

        $semesterOptionsBase = $this->cachedSemesterOptionsFromDateColumn(
            'order_notes.index.semester_options.base',
            OrderNote::class,
            'note_date'
        );
        $semesterOptions = $this->semesterOptionsForIndex($semesterOptionsBase, $isAdminUser);
        $selectedSemester = $this->selectedSemesterIfAvailable($selectedSemester, $semesterOptions);
        if ($selectedSemester === null) {
            $semesterRange = null;
        }

        $notes = OrderNote::query()
            ->onlyListColumns()
            ->withCustomerInfo()
            ->searchKeyword($search)
            ->when($semesterRange !== null, function ($query) use ($semesterRange): void {
                $query->whereBetween('note_date', [$semesterRange['start'], $semesterRange['end']]);
            })
            ->when($selectedStatus === 'active', fn($query) => $query->active())
            ->when($selectedStatus === 'canceled', fn($query) => $query->canceled())
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
            AppCache::lookupCacheKey('order_notes.index.today_summary', [
                'status' => $selectedStatus ?? 'all',
                'date' => $now->toDateString(),
            ]),
            $now->copy()->addSeconds(30),
            function () use ($todayRange, $selectedStatus) {
                return (object) [
                    'total_notes' => (int) OrderNote::query()
                        ->whereBetween('note_date', $todayRange)
                        ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                            $query->where('is_canceled', $selectedStatus === 'canceled');
                        })
                        ->count(),
                    'total_qty' => (int) OrderNoteItem::query()
                        ->join('order_notes', 'order_note_items.order_note_id', '=', 'order_notes.id')
                        ->whereBetween('order_notes.note_date', $todayRange)
                        ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                            $query->where('order_notes.is_canceled', $selectedStatus === 'canceled');
                        })
                        ->sum('order_note_items.quantity'),
                ];
            }
        );

        return view('order_notes.index', [
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
        $now = now();
        $oldCustomerId = (int) old('customer_id', 0);
        $customers = Cache::remember(
            AppCache::lookupCacheKey('forms.order_notes.customers', ['limit' => 20]),
            $now->copy()->addSeconds(60),
            fn() => Customer::query()
                ->onlyOrderFormColumns()
                ->orderBy('name')
                ->limit(20)
                ->get()
        );
        if ($oldCustomerId > 0 && ! $customers->contains('id', $oldCustomerId)) {
            $oldCustomer = Customer::query()
                ->onlyOrderFormColumns()
                ->whereKey($oldCustomerId)
                ->first();
            if ($oldCustomer !== null) {
                $customers->prepend($oldCustomer);
            }
        }
        $customers = $customers->unique('id')->values();

        $oldProductIds = collect(old('items', []))
            ->pluck('product_id')
            ->map(fn($id): int => (int) $id)
            ->filter(fn(int $id): bool => $id > 0)
            ->values();
        $products = Cache::remember(
            AppCache::lookupCacheKey('forms.order_notes.products', ['limit' => 20, 'active_only' => 1]),
            $now->copy()->addSeconds(60),
            fn() => Product::query()
                ->onlyOrderFormColumns()
                ->active()
                ->orderBy('name')
                ->limit(20)
                ->get()
        );
        if ($oldProductIds->isNotEmpty()) {
            $oldProducts = Product::query()
                ->onlyOrderFormColumns()
                ->whereIn('id', $oldProductIds->all())
                ->get();
            $products = $oldProducts->concat($products)->unique('id')->values();
        }

        return view('order_notes.create', [
            'customers' => $customers,
            'products' => $products,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'note_date' => ['required', 'date'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_code' => ['nullable', 'string', 'max:60'],
            'items.*.product_name' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $note = DB::transaction(function () use ($data): OrderNote {
            $noteDate = $data['note_date'];
            $noteNumber = $this->generateNoteNumber($noteDate);

            $note = OrderNote::create([
                'note_number' => $noteNumber,
                'note_date' => $noteDate,
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'city' => $data['city'] ?? null,
                'created_by_name' => auth()->user()?->name ?? __('txn.system_user'),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $productId = $row['product_id'] ?? null;
                $productCode = $row['product_code'] ?? null;
                $productName = $row['product_name'];

                if ($productId) {
                    $product = Product::query()->find($productId);
                    if ($product) {
                        $productCode = $productCode ?: $product->code;
                        $productName = $productName ?: $product->name;
                    }
                }

                OrderNoteItem::create([
                    'order_note_id' => $note->id,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                    'product_name' => $productName,
                    'quantity' => $row['quantity'],
                    'notes' => $row['notes'] ?? null,
                ]);
            }

            return $note;
        });

        $this->auditLogService->log(
            'order.note.create',
            $note,
            __('txn.audit_order_created', ['number' => $note->note_number]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $note->note_date]);

        return redirect()
            ->route('order-notes.show', $note)
            ->with('success', __('txn.order_note_created_success', ['number' => $note->note_number]));
    }

    public function show(OrderNote $orderNote): View
    {
        $now = now();
        $orderNote->load(['customer:id,name,city,phone', 'items']);
        $itemProductIds = $orderNote->items
            ->pluck('product_id')
            ->map(fn($id): int => (int) $id)
            ->filter(fn(int $id): bool => $id > 0)
            ->values();
        $products = Cache::remember(
            AppCache::lookupCacheKey('forms.order_notes.products', ['limit' => 20, 'active_only' => 1]),
            $now->copy()->addSeconds(60),
            fn() => Product::query()
                ->onlyOrderFormColumns()
                ->active()
                ->orderBy('name')
                ->limit(20)
                ->get()
        );
        if ($itemProductIds->isNotEmpty()) {
            $itemProducts = Product::query()
                ->onlyOrderFormColumns()
                ->whereIn('id', $itemProductIds->all())
                ->get();
            $products = $itemProducts->concat($products)->unique('id')->values();
        }

        return view('order_notes.show', [
            'note' => $orderNote,
            'products' => $products,
        ]);
    }

    public function adminUpdate(Request $request, OrderNote $orderNote): RedirectResponse
    {
        $data = $request->validate([
            'note_date' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_name' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($orderNote, $data): void {
            $note = OrderNote::query()
                ->with('items')
                ->whereKey($orderNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            $note->update([
                'note_date' => $data['note_date'],
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'city' => $data['city'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $note->items()->delete();

            foreach ($data['items'] as $row) {
                $productId = $row['product_id'] ?? null;
                $productCode = null;
                $productName = $row['product_name'];
                if ($productId) {
                    $product = Product::query()->find($productId);
                    if ($product) {
                        $productCode = $product->code;
                        $productName = $product->name;
                    }
                }

                OrderNoteItem::create([
                    'order_note_id' => $note->id,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                    'product_name' => $productName,
                    'quantity' => $row['quantity'],
                    'notes' => $row['notes'] ?? null,
                ]);
            }
        });

        $orderNote->refresh();
        $this->auditLogService->log(
            'order.note.admin_update',
            $orderNote,
            __('txn.audit_order_admin_updated', ['number' => $orderNote->note_number]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $orderNote->note_date]);

        return redirect()
            ->route('order-notes.show', $orderNote)
            ->with('success', __('txn.admin_update_saved'));
    }

    public function cancel(Request $request, OrderNote $orderNote): RedirectResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:1000'],
        ]);

        $orderNote->update([
            'is_canceled' => true,
            'canceled_at' => now(),
            'canceled_by_user_id' => auth()->id(),
            'cancel_reason' => $data['cancel_reason'],
        ]);

        $this->auditLogService->log(
            'order.note.cancel',
            $orderNote,
            __('txn.audit_order_canceled', ['number' => $orderNote->note_number]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $orderNote->note_date]);

        return redirect()
            ->route('order-notes.show', $orderNote)
            ->with('success', __('txn.transaction_canceled_success'));
    }

    public function print(OrderNote $orderNote): View
    {
        $orderNote->load(['customer:id,name,city,phone', 'items']);

        return view('order_notes.print', [
            'note' => $orderNote,
        ]);
    }

    public function exportPdf(OrderNote $orderNote)
    {
        $orderNote->load(['customer:id,name,city,phone', 'items']);

        $filename = $orderNote->note_number . '.pdf';
        $pdf = Pdf::loadView('order_notes.print', [
            'note' => $orderNote,
            'isPdf' => true,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    public function exportExcel(OrderNote $orderNote): StreamedResponse
    {
        $orderNote->load(['customer:id,name,city,phone', 'items']);
        $filename = $orderNote->note_number . '.xlsx';

        return response()->streamDownload(function () use ($orderNote): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Surat Pesanan');
            $rows = [];
            $rows[] = [__('txn.order_notes_title') . ' ' . __('txn.note_number'), $orderNote->note_number];
            $rows[] = [__('txn.date'), $orderNote->note_date?->format('d-m-Y')];
            $rows[] = [__('txn.customer'), $orderNote->customer_name];
            $rows[] = [__('txn.phone'), $orderNote->customer_phone];
            $rows[] = [__('txn.city'), $orderNote->city];
            $rows[] = [__('txn.created_by'), $orderNote->created_by_name];
            $rows[] = [__('txn.notes'), $orderNote->notes];
            $rows[] = [];
            $rows[] = [__('txn.items')];
            $rows[] = [__('txn.name'), __('txn.qty'), __('txn.notes')];

            foreach ($orderNote->items as $item) {
                $rows[] = [
                    $item->product_name,
                    $item->quantity,
                    $item->notes,
                ];
            }

            $sheet->fromArray($rows, null, 'A1');
            $itemsCount = $orderNote->items->count();
            $itemsHeaderRow = 10;
            ExcelExportStyler::styleTable($sheet, $itemsHeaderRow, 3, $itemsCount, true);
            ExcelExportStyler::formatNumberColumns($sheet, $itemsHeaderRow + 1, $itemsHeaderRow + $itemsCount, [2], '#,##0');

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
        $prefix = 'PO-' . date('Ymd', strtotime($date));
        $count = OrderNote::query()
            ->whereDate('note_date', $date)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }

    private function currentSemesterPeriod(): string
    {
        return $this->semesterBookService->currentSemester();
    }

    private function previousSemesterPeriod(string $period): string
    {
        return $this->semesterBookService->previousSemester($period);
    }

    private function semesterDateRange(?string $period): ?array
    {
        return $this->semesterBookService->semesterDateRange($period);
    }

    private function semesterPeriodFromDate(Carbon|string|null $date): string
    {
        $rawDate = $date instanceof Carbon ? $date->format('Y-m-d') : (string) $date;
        return $this->semesterBookService->semesterFromDate($rawDate) ?? $this->currentSemesterPeriod();
    }
}
