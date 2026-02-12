<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\OrderNote;
use App\Models\OrderNoteItem;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Support\SemesterBookService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderNotePageController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));
        $semester = trim((string) $request->string('semester', ''));
        $status = trim((string) $request->string('status', ''));
        $noteDate = trim((string) $request->string('note_date', ''));
        $selectedStatus = in_array($status, ['active', 'canceled'], true) ? $status : null;
        $selectedSemester = $semester !== '' ? $semester : null;
        $selectedNoteDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $noteDate) === 1 ? $noteDate : null;

        $currentSemester = $this->currentSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);
        $semesterRange = $this->semesterDateRange($selectedSemester);

        $semesterOptions = OrderNote::query()
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
        $semesterOptions = collect($this->semesterBookService()->filterToActiveSemesters($semesterOptions->all()));
        if ($selectedSemester !== null && ! $semesterOptions->contains($selectedSemester)) {
            $selectedSemester = null;
            $semesterRange = null;
        }

        $notes = OrderNote::query()
            ->with('customer:id,name,city')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('note_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->when($semesterRange !== null, function ($query) use ($semesterRange): void {
                $query->whereBetween('note_date', [$semesterRange['start'], $semesterRange['end']]);
            })
            ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                $query->where('is_canceled', $selectedStatus === 'canceled');
            })
            ->when($selectedNoteDate !== null, function ($query) use ($selectedNoteDate): void {
                $query->whereDate('note_date', $selectedNoteDate);
            })
            ->latest('note_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $summaryQuery = OrderNote::query()
            ->when($semesterRange !== null, function ($query) use ($semesterRange): void {
                $query->whereBetween('note_date', [$semesterRange['start'], $semesterRange['end']]);
            })
            ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                $query->where('is_canceled', $selectedStatus === 'canceled');
            })
            ->when($selectedNoteDate !== null, function ($query) use ($selectedNoteDate): void {
                $query->whereDate('note_date', $selectedNoteDate);
            });

        $summary = (object) [
            'total_notes' => (int) $summaryQuery->count(),
            'total_qty' => (int) OrderNoteItem::query()
                ->join('order_notes', 'order_note_items.order_note_id', '=', 'order_notes.id')
                ->when($semesterRange !== null, function ($query) use ($semesterRange): void {
                    $query->whereBetween('order_notes.note_date', [$semesterRange['start'], $semesterRange['end']]);
                })
                ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                    $query->where('order_notes.is_canceled', $selectedStatus === 'canceled');
                })
                ->when($selectedNoteDate !== null, function ($query) use ($selectedNoteDate): void {
                    $query->whereDate('order_notes.note_date', $selectedNoteDate);
                })
                ->sum('order_note_items.quantity'),
        ];

        return view('order_notes.index', [
            'notes' => $notes,
            'search' => $search,
            'semesterOptions' => $semesterOptions,
            'selectedSemester' => $selectedSemester,
            'selectedStatus' => $selectedStatus,
            'selectedNoteDate' => $selectedNoteDate,
            'currentSemester' => $currentSemester,
            'previousSemester' => $previousSemester,
            'summary' => $summary,
        ]);
    }

    public function create(): View
    {
        return view('order_notes.create', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'city', 'phone']),
            'products' => Product::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
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

        return redirect()
            ->route('order-notes.show', $note)
            ->with('success', __('txn.order_note_created_success', ['number' => $note->note_number]));
    }

    public function show(OrderNote $orderNote): View
    {
        $orderNote->load(['customer:id,name,city,phone', 'items']);

        return view('order_notes.show', [
            'note' => $orderNote,
            'products' => Product::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
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

        $filename = $orderNote->note_number.'.pdf';
        $pdf = Pdf::loadView('order_notes.print', [
            'note' => $orderNote,
            'isPdf' => true,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    public function exportExcel(OrderNote $orderNote): StreamedResponse
    {
        $orderNote->load(['customer:id,name,city,phone', 'items']);
        $filename = $orderNote->note_number.'.csv';

        return response()->streamDownload(function () use ($orderNote): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [__('txn.order_notes_title').' '.__('txn.note_number'), $orderNote->note_number]);
            fputcsv($handle, [__('txn.date'), $orderNote->note_date?->format('d-m-Y')]);
            fputcsv($handle, [__('txn.customer'), $orderNote->customer_name]);
            fputcsv($handle, [__('txn.phone'), $orderNote->customer_phone]);
            fputcsv($handle, [__('txn.city'), $orderNote->city]);
            fputcsv($handle, [__('txn.created_by'), $orderNote->created_by_name]);
            fputcsv($handle, [__('txn.notes'), $orderNote->notes]);
            fputcsv($handle, []);
            fputcsv($handle, [__('txn.items')]);
            fputcsv($handle, [__('txn.name'), __('txn.qty'), __('txn.notes')]);

            foreach ($orderNote->items as $item) {
                fputcsv($handle, [
                    $item->product_name,
                    $item->quantity,
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
        $prefix = 'PO-'.date('Ymd', strtotime($date));
        $count = OrderNote::query()
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
        return collect(preg_split('/[\r\n,]+/', (string) AppSetting::getValue('semester_period_options', '')) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '');
    }

    private function semesterBookService(): SemesterBookService
    {
        return app(SemesterBookService::class);
    }
}
