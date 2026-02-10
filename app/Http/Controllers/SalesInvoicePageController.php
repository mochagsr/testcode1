<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\AppSetting;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\StockMutation;
use App\Services\ReceivableLedgerService;
use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesInvoicePageController extends Controller
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

        $semesterOptions = SalesInvoice::query()
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

        $invoices = SalesInvoice::query()
            ->with('customer:id,name,city')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                            $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('city', 'like', "%{$search}%");
                        });
                });
            })
            ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                $query->where('semester_period', $selectedSemester);
            })
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $semesterSummary = SalesInvoice::query()
            ->selectRaw('COUNT(*) as total_invoice, COALESCE(SUM(total), 0) as grand_total, COALESCE(SUM(total_paid), 0) as paid_total, COALESCE(SUM(balance), 0) as balance_total')
            ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                $query->where('semester_period', $selectedSemester);
            })
            ->first();

        return view('sales_invoices.index', [
            'invoices' => $invoices,
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
        $defaultSemester = $this->defaultSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($defaultSemester);
        $configured = collect(explode(',', (string) AppSetting::getValue('semester_period_options', '')))
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '');

        $semesterOptions = SalesInvoice::query()
            ->whereNotNull('semester_period')
            ->where('semester_period', '!=', '')
            ->distinct()
            ->pluck('semester_period')
            ->merge($configured)
            ->push($defaultSemester)
            ->push($previousSemester)
            ->unique()
            ->sortDesc()
            ->values();

        return view('sales_invoices.create', [
            'customers' => Customer::query()
                ->with('level:id,code,name')
                ->orderBy('name')
                ->get(['id', 'name', 'city', 'customer_level_id']),
            'products' => Product::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'stock', 'price_agent', 'price_sales', 'price_general']),
            'defaultSemesterPeriod' => $defaultSemester,
            'semesterOptions' => $semesterOptions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'semester_period' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'payment_method' => ['nullable', 'in:cash,bank_transfer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $invoice = DB::transaction(function () use ($data): SalesInvoice {
            $invoiceDate = Carbon::parse($data['invoice_date']);
            $invoiceNumber = $this->generateInvoiceNumber($invoiceDate->toDateString());
            $rows = collect($data['items']);

            $products = Product::query()
                ->whereIn('id', $rows->pluck('product_id')->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0.0;
            $computedRows = [];

            foreach ($rows as $index => $row) {
                $product = $products->get((int) $row['product_id']);
                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => 'Product not found.',
                    ]);
                }

                $quantity = (int) $row['quantity'];
                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => "Insufficient stock for {$product->name}.",
                    ]);
                }

                $unitPrice = (float) $row['unit_price'];
                $discount = (float) ($row['discount'] ?? 0);
                $lineTotal = max(0, ($quantity * $unitPrice) - $discount);
                $subtotal += $lineTotal;

                $computedRows[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'line_total' => $lineTotal,
                ];
            }

            $invoice = SalesInvoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $data['customer_id'],
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'semester_period' => $data['semester_period'] ?? $this->defaultSemesterPeriod(),
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'total_paid' => 0,
                'balance' => $subtotal,
                'payment_status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($computedRows as $row) {
                /** @var Product $product */
                $product = $row['product'];

                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'product_code' => $product->code,
                    'product_name' => $product->name,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'discount' => $row['discount'],
                    'line_total' => $row['line_total'],
                ]);

                $product->decrement('stock', $row['quantity']);

                StockMutation::create([
                    'product_id' => $product->id,
                    'reference_type' => SalesInvoice::class,
                    'reference_id' => $invoice->id,
                    'mutation_type' => 'out',
                    'quantity' => $row['quantity'],
                    'notes' => "Sales invoice {$invoice->invoice_number}",
                    'created_by_user_id' => null,
                ]);
            }

            $this->receivableLedgerService->addDebit(
                customerId: (int) $invoice->customer_id,
                invoiceId: (int) $invoice->id,
                entryDate: $invoiceDate,
                amount: $subtotal,
                periodCode: $invoice->semester_period,
                description: "Invoice {$invoice->invoice_number}"
            );

            $initialPayment = !empty($data['payment_method']) ? (float) $invoice->total : 0.0;
            if ($initialPayment > 0) {
                InvoicePayment::create([
                    'sales_invoice_id' => $invoice->id,
                    'payment_date' => $invoiceDate->toDateString(),
                    'amount' => $initialPayment,
                    'method' => (string) $data['payment_method'],
                    'notes' => 'Full payment on invoice creation',
                ]);

                $balance = max(0, (float) $invoice->total - $initialPayment);
                $invoice->update([
                    'total_paid' => $initialPayment,
                    'balance' => $balance,
                    'payment_status' => $balance <= 0 ? 'paid' : 'partial',
                ]);

                $this->receivableLedgerService->addCredit(
                    customerId: (int) $invoice->customer_id,
                    invoiceId: (int) $invoice->id,
                    entryDate: $invoiceDate,
                    amount: $initialPayment,
                    periodCode: $invoice->semester_period,
                    description: "Payment for {$invoice->invoice_number}"
                );
            }

            return $invoice;
        });

        $this->auditLogService->log('sales.invoice.create', $invoice, "Invoice created: {$invoice->invoice_number}", $request);

        return redirect()
            ->route('sales-invoices.show', $invoice)
            ->with('success', "Invoice {$invoice->invoice_number} has been created.");
    }

    public function show(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load([
            'customer:id,name,city,phone',
            'items.product:id,code,name',
            'payments',
        ]);

        return view('sales_invoices.show', [
            'invoice' => $salesInvoice,
        ]);
    }

    public function print(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load([
            'customer:id,name,city,phone,address',
            'items',
            'payments',
        ]);

        return view('sales_invoices.print', [
            'invoice' => $salesInvoice,
        ]);
    }

    public function exportPdf(SalesInvoice $salesInvoice)
    {
        $salesInvoice->load([
            'customer:id,name,city,phone,address',
            'items',
            'payments',
        ]);

        $filename = $salesInvoice->invoice_number.'.pdf';
        $pdf = Pdf::loadView('sales_invoices.print', [
            'invoice' => $salesInvoice,
            'isPdf' => true,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    public function exportExcel(SalesInvoice $salesInvoice): StreamedResponse
    {
        $salesInvoice->load([
            'customer:id,name,city,phone,address',
            'items',
            'payments',
        ]);

        $filename = $salesInvoice->invoice_number.'.csv';

        return response()->streamDownload(function () use ($salesInvoice): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Invoice Number', $salesInvoice->invoice_number]);
            fputcsv($handle, ['Invoice Date', $salesInvoice->invoice_date?->format('d-m-Y')]);
            fputcsv($handle, ['Customer', $salesInvoice->customer?->name]);
            fputcsv($handle, ['City', $salesInvoice->customer?->city]);
            fputcsv($handle, ['Status', strtoupper((string) $salesInvoice->payment_status)]);
            fputcsv($handle, ['Total', $salesInvoice->total]);
            fputcsv($handle, ['Paid', $salesInvoice->total_paid]);
            fputcsv($handle, ['Balance', $salesInvoice->balance]);
            fputcsv($handle, []);
            fputcsv($handle, ['Items']);
            fputcsv($handle, ['Code', 'Name', 'Qty', 'Unit Price', 'Discount', 'Line Total']);

            foreach ($salesInvoice->items as $item) {
                fputcsv($handle, [
                    $item->product_code,
                    $item->product_name,
                    $item->quantity,
                    $item->unit_price,
                    $item->discount,
                    $item->line_total,
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Payments']);
            fputcsv($handle, ['Date', 'Method', 'Amount', 'Notes']);
            foreach ($salesInvoice->payments as $payment) {
                fputcsv($handle, [
                    $payment->payment_date?->format('d-m-Y'),
                    $payment->method,
                    $payment->amount,
                    $payment->notes,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function generateInvoiceNumber(string $date): string
    {
        $prefix = 'INV-'.date('Ymd', strtotime($date));
        $count = SalesInvoice::query()
            ->whereDate('invoice_date', $date)
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
