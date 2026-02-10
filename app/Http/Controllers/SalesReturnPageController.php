<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockMutation;
use App\Services\AuditLogService;
use App\Services\ReceivableLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReturnPageController extends Controller
{
    public function __construct(
        private readonly ReceivableLedgerService $receivableLedgerService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));
        $semester = trim((string) $request->string('semester', ''));
        $selectedSemester = $semester !== '' ? $semester : null;

        $currentSemester = $this->defaultSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);

        $semesterOptions = SalesReturn::query()
            ->whereNotNull('semester_period')
            ->where('semester_period', '!=', '')
            ->distinct()
            ->pluck('semester_period')
            ->merge($this->configuredSemesterOptions())
            ->push($currentSemester)
            ->push($previousSemester)
            ->unique()
            ->sortDesc()
            ->values();

        $returns = SalesReturn::query()
            ->with('customer:id,name,city')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('return_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                            $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('city', 'like', "%{$search}%");
                        });
                });
            })
            ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                $query->where('semester_period', $selectedSemester);
            })
            ->latest('return_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $semesterSummary = SalesReturn::query()
            ->selectRaw('COUNT(*) as total_return, COALESCE(SUM(total), 0) as grand_total')
            ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                $query->where('semester_period', $selectedSemester);
            })
            ->first();

        return view('sales_returns.index', [
            'returns' => $returns,
            'search' => $search,
            'semesterOptions' => $semesterOptions,
            'selectedSemester' => $selectedSemester,
            'currentSemester' => $currentSemester,
            'previousSemester' => $previousSemester,
            'semesterSummary' => $semesterSummary,
        ]);
    }

    public function create(): View
    {
        $currentSemester = $this->defaultSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);
        $semesterOptions = SalesReturn::query()
            ->whereNotNull('semester_period')
            ->where('semester_period', '!=', '')
            ->distinct()
            ->pluck('semester_period')
            ->merge($this->configuredSemesterOptions())
            ->push($currentSemester)
            ->push($previousSemester)
            ->unique()
            ->sortDesc()
            ->values();

        return view('sales_returns.create', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'city']),
            'products' => Product::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'stock', 'price_general']),
            'semesterOptions' => $semesterOptions,
            'defaultSemesterPeriod' => $this->defaultSemesterPeriod(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'return_date' => ['required', 'date'],
            'semester_period' => ['nullable', 'string', 'max:30'],
            'reason' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $salesReturn = DB::transaction(function () use ($data): SalesReturn {
            $returnDate = Carbon::parse($data['return_date']);
            $returnNumber = $this->generateReturnNumber($returnDate->toDateString());
            $rows = collect($data['items']);

            $products = Product::query()
                ->whereIn('id', $rows->pluck('product_id')->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $total = 0.0;
            $computedRows = [];

            foreach ($rows as $index => $row) {
                $product = $products->get((int) $row['product_id']);
                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => 'Product not found.',
                    ]);
                }

                $quantity = (int) $row['quantity'];
                $unitPrice = (float) $product->price_general;
                $lineTotal = $quantity * $unitPrice;
                $total += $lineTotal;

                $computedRows[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $salesReturn = SalesReturn::create([
                'return_number' => $returnNumber,
                'customer_id' => $data['customer_id'],
                'return_date' => $returnDate->toDateString(),
                'semester_period' => $data['semester_period'] ?? $this->defaultSemesterPeriod(),
                'total' => $total,
                'reason' => $data['reason'] ?? null,
            ]);

            foreach ($computedRows as $row) {
                /** @var Product $product */
                $product = $row['product'];

                SalesReturnItem::create([
                    'sales_return_id' => $salesReturn->id,
                    'product_id' => $product->id,
                    'product_code' => $product->code,
                    'product_name' => $product->name,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'line_total' => $row['line_total'],
                ]);

                $product->increment('stock', $row['quantity']);

                StockMutation::create([
                    'product_id' => $product->id,
                    'reference_type' => SalesReturn::class,
                    'reference_id' => $salesReturn->id,
                    'mutation_type' => 'in',
                    'quantity' => $row['quantity'],
                    'notes' => "Sales return {$salesReturn->return_number}",
                    'created_by_user_id' => null,
                ]);
            }

            $this->receivableLedgerService->addCredit(
                customerId: (int) $salesReturn->customer_id,
                invoiceId: null,
                entryDate: $returnDate,
                amount: $total,
                periodCode: $salesReturn->semester_period,
                description: "Sales return {$salesReturn->return_number}"
            );

            return $salesReturn;
        });

        $this->auditLogService->log('sales.return.create', $salesReturn, "Sales return created: {$salesReturn->return_number}", $request);

        return redirect()
            ->route('sales-returns.show', $salesReturn)
            ->with('success', "Sales return {$salesReturn->return_number} has been created.");
    }

    public function show(SalesReturn $salesReturn): View
    {
        $salesReturn->load([
            'customer:id,name,city,phone',
            'items',
        ]);

        return view('sales_returns.show', [
            'salesReturn' => $salesReturn,
        ]);
    }

    public function print(SalesReturn $salesReturn): View
    {
        $salesReturn->load([
            'customer:id,name,city,phone,address',
            'items',
        ]);

        return view('sales_returns.print', [
            'salesReturn' => $salesReturn,
        ]);
    }

    public function exportPdf(SalesReturn $salesReturn)
    {
        $salesReturn->load([
            'customer:id,name,city,phone,address',
            'items',
        ]);

        $filename = $salesReturn->return_number.'.pdf';
        $pdf = Pdf::loadView('sales_returns.print', [
            'salesReturn' => $salesReturn,
            'isPdf' => true,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    public function exportExcel(SalesReturn $salesReturn): StreamedResponse
    {
        $salesReturn->load([
            'customer:id,name,city,phone,address',
            'items',
        ]);

        $filename = $salesReturn->return_number.'.csv';

        return response()->streamDownload(function () use ($salesReturn): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Return Number', $salesReturn->return_number]);
            fputcsv($handle, ['Return Date', $salesReturn->return_date?->format('d-m-Y')]);
            fputcsv($handle, ['Customer', $salesReturn->customer?->name]);
            fputcsv($handle, ['City', $salesReturn->customer?->city]);
            fputcsv($handle, ['Semester', $salesReturn->semester_period]);
            fputcsv($handle, ['Total', $salesReturn->total]);
            fputcsv($handle, ['Reason', $salesReturn->reason]);
            fputcsv($handle, []);
            fputcsv($handle, ['Items']);
            fputcsv($handle, ['Code', 'Name', 'Qty', 'Line Total']);

            foreach ($salesReturn->items as $item) {
                fputcsv($handle, [
                    $item->product_code,
                    $item->product_name,
                    $item->quantity,
                    $item->line_total,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function generateReturnNumber(string $date): string
    {
        $prefix = 'RTN-'.date('Ymd', strtotime($date));
        $count = SalesReturn::query()
            ->whereDate('return_date', $date)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }

    private function defaultSemesterPeriod(): string
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
        $year = $previous->year;

        return "S{$semester}-{$year}";
    }

    private function configuredSemesterOptions()
    {
        return collect(explode(',', (string) AppSetting::getValue('semester_period_options', '')))
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '');
    }
}
