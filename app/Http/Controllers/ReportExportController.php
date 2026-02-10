<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\OrderNote;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function index(Request $request): View
    {
        $selectedSemester = $this->selectedSemester($request);
        $selectedCustomerId = $this->selectedCustomerId($request);

        return view('reports.index', [
            'datasets' => $this->datasets(),
            'selectedSemester' => $selectedSemester,
            'selectedCustomerId' => $selectedCustomerId,
            'semesterOptions' => $this->semesterOptions(),
            'semesterEnabledDatasets' => ['sales_invoices', 'sales_returns', 'delivery_notes', 'order_notes', 'receivables'],
            'receivableCustomers' => Customer::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function exportCsv(Request $request, string $dataset): StreamedResponse
    {
        $selectedSemester = $this->selectedSemester($request);
        $selectedCustomerId = $this->selectedCustomerId($request);
        $report = $this->reportData($dataset, $selectedSemester, $selectedCustomerId);
        $filename = $dataset.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [$report['title']]);
            fputcsv($handle, [__('report.printed'), now()->format('d-m-Y H:i:s')]);

            if (! empty($report['filters'])) {
                foreach ($report['filters'] as $filter) {
                    fputcsv($handle, [$filter['label'], $filter['value']]);
                }
            }

            if (! empty($report['summary'])) {
                foreach ($report['summary'] as $item) {
                    $value = ($item['type'] ?? 'number') === 'currency'
                        ? 'Rp '.number_format((float) ($item['value'] ?? 0), 2, '.', '')
                        : number_format((float) ($item['value'] ?? 0), 0, '.', '');

                    fputcsv($handle, [$item['label'], $value]);
                }
            }

            fputcsv($handle, []);
            fputcsv($handle, $report['headers']);
            foreach ($report['rows'] as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function print(Request $request, string $dataset): View
    {
        $report = $this->reportData(
            $dataset,
            $this->selectedSemester($request),
            $this->selectedCustomerId($request)
        );

        return view('reports.print', [
            'title' => $report['title'],
            'headers' => $report['headers'],
            'rows' => $report['rows'],
            'summary' => $report['summary'],
            'filters' => $report['filters'],
            'printedAt' => now(),
        ]);
    }

    public function exportPdf(Request $request, string $dataset)
    {
        $report = $this->reportData(
            $dataset,
            $this->selectedSemester($request),
            $this->selectedCustomerId($request)
        );
        $filename = $dataset.'-'.now()->format('Ymd-His').'.pdf';

        $pdf = Pdf::loadView('reports.pdf', [
            'title' => $report['title'],
            'headers' => $report['headers'],
            'rows' => $report['rows'],
            'summary' => $report['summary'],
            'filters' => $report['filters'],
            'printedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    /**
     * @return array<string, string>
     */
    private function datasets(): array
    {
        return [
            'products' => __('report.datasets.products'),
            'customers' => __('report.datasets.customers'),
            'sales_invoices' => __('report.datasets.sales_invoices'),
            'receivables' => __('report.datasets.receivables'),
            'sales_returns' => __('report.datasets.sales_returns'),
            'delivery_notes' => __('report.datasets.delivery_notes'),
            'order_notes' => __('report.datasets.order_notes'),
        ];
    }

    /**
     * @return array{title:string,headers:array<int,string>,rows:callable():array<int,array<int,string|int|float|null>>}
     */
    private function datasetConfig(string $dataset, ?string $selectedSemester = null, ?int $selectedCustomerId = null): array
    {
        $semesterRange = $this->semesterDateRange($selectedSemester);

        return match ($dataset) {
            'products' => [
                'title' => __('report.titles.products'),
                'headers' => ['Code', 'Name', 'Category', 'Stock', 'Price Agent', 'Price Sales', 'Price General', 'Active'],
                'rows' => function (): array {
                    return Product::query()
                        ->with('category:id,name')
                        ->orderBy('name')
                        ->get()
                        ->map(fn (Product $row): array => [
                            $row->code,
                            $row->name,
                            $row->category?->name,
                            $row->stock,
                            $row->price_agent,
                            $row->price_sales,
                            $row->price_general,
                            $row->is_active ? 'Yes' : 'No',
                        ])
                        ->all();
                },
            ],
            'customers' => [
                'title' => __('report.titles.customers'),
                'headers' => ['Name', 'Level', 'Phone', 'City', 'Outstanding Receivable'],
                'rows' => function (): array {
                    return Customer::query()
                        ->with('level:id,name')
                        ->orderBy('name')
                        ->get()
                        ->map(fn (Customer $row): array => [
                            $row->name,
                            $row->level?->name,
                            $row->phone,
                            $row->city,
                            $row->outstanding_receivable,
                        ])
                        ->all();
                },
            ],
            'sales_invoices' => [
                'title' => __('report.titles.sales_invoices'),
                'headers' => ['Invoice No', 'Date', 'Customer', 'City', 'Total', 'Paid', 'Balance', 'Status', 'Semester'],
                'rows' => function () use ($selectedSemester): array {
                    return SalesInvoice::query()
                        ->with('customer:id,name,city')
                        ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                            $query->where('semester_period', $selectedSemester);
                        })
                        ->latest('invoice_date')
                        ->get()
                        ->map(fn (SalesInvoice $row): array => [
                            $row->invoice_number,
                            $row->invoice_date?->format('d-m-Y'),
                            $row->customer?->name,
                            $row->customer?->city,
                            $row->total,
                            $row->total_paid,
                            $row->balance,
                            $row->payment_status,
                            $row->semester_period,
                        ])
                        ->all();
                },
            ],
            'receivables' => [
                'title' => __('report.titles.receivables'),
                'headers' => ['Invoice No', 'Date', 'Customer', 'City', 'Semester', 'Total', 'Paid', 'Balance', 'Status'],
                'rows' => function () use ($selectedSemester, $selectedCustomerId): array {
                    return SalesInvoice::query()
                        ->with('customer:id,name,city')
                        ->where('balance', '>', 0)
                        ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                            $query->where('semester_period', $selectedSemester);
                        })
                        ->when($selectedCustomerId !== null, function ($query) use ($selectedCustomerId): void {
                            $query->where('customer_id', $selectedCustomerId);
                        })
                        ->latest('invoice_date')
                        ->get()
                        ->map(fn (SalesInvoice $row): array => [
                            $row->invoice_number,
                            $row->invoice_date?->format('d-m-Y'),
                            $row->customer?->name,
                            $row->customer?->city,
                            $row->semester_period,
                            $row->total,
                            $row->total_paid,
                            $row->balance,
                            $row->payment_status,
                        ])
                        ->all();
                },
            ],
            'sales_returns' => [
                'title' => __('report.titles.sales_returns'),
                'headers' => ['Return No', 'Date', 'Customer', 'City', 'Total', 'Semester'],
                'rows' => function () use ($selectedSemester): array {
                    return SalesReturn::query()
                        ->with('customer:id,name,city')
                        ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                            $query->where('semester_period', $selectedSemester);
                        })
                        ->latest('return_date')
                        ->get()
                        ->map(fn (SalesReturn $row): array => [
                            $row->return_number,
                            $row->return_date?->format('d-m-Y'),
                            $row->customer?->name,
                            $row->customer?->city,
                            $row->total,
                            $row->semester_period,
                        ])
                        ->all();
                },
            ],
            'delivery_notes' => [
                'title' => __('report.titles.delivery_notes'),
                'headers' => ['Note No', 'Date', 'Recipient', 'Phone', 'City', 'Created By'],
                'rows' => function () use ($semesterRange): array {
                    return DeliveryNote::query()
                        ->when($semesterRange !== null, function ($query) use ($semesterRange): void {
                            $query->whereBetween('note_date', [$semesterRange['start'], $semesterRange['end']]);
                        })
                        ->latest('note_date')
                        ->get()
                        ->map(fn (DeliveryNote $row): array => [
                            $row->note_number,
                            $row->note_date?->format('d-m-Y'),
                            $row->recipient_name,
                            $row->recipient_phone,
                            $row->city,
                            $row->created_by_name,
                        ])
                        ->all();
                },
            ],
            'order_notes' => [
                'title' => __('report.titles.order_notes'),
                'headers' => ['Note No', 'Date', 'Customer', 'Phone', 'City', 'Created By'],
                'rows' => function () use ($semesterRange): array {
                    return OrderNote::query()
                        ->when($semesterRange !== null, function ($query) use ($semesterRange): void {
                            $query->whereBetween('note_date', [$semesterRange['start'], $semesterRange['end']]);
                        })
                        ->latest('note_date')
                        ->get()
                        ->map(fn (OrderNote $row): array => [
                            $row->note_number,
                            $row->note_date?->format('d-m-Y'),
                            $row->customer_name,
                            $row->customer_phone,
                            $row->city,
                            $row->created_by_name,
                        ])
                        ->all();
                },
            ],
            default => abort(404),
        };
    }

    /**
     * @return array{title:string,headers:array<int,string>,rows:array<int,array<int,string|int|float|null>>,summary:array<int,array{label:string,value:int|float,type:string}>|null,filters:array<int,array{label:string,value:string}>|null}
     */
    private function reportData(string $dataset, ?string $selectedSemester = null, ?int $selectedCustomerId = null): array
    {
        $config = $this->datasetConfig($dataset, $selectedSemester, $selectedCustomerId);
        $summary = null;
        $filters = null;
        if ($dataset === 'receivables') {
            $summary = $this->receivableSummary($selectedSemester, $selectedCustomerId);
            $filters = $this->receivableFilters($selectedSemester, $selectedCustomerId);
        }

        return [
            'title' => $config['title'],
            'headers' => $config['headers'],
            'rows' => $config['rows'](),
            'summary' => $summary,
            'filters' => $filters,
        ];
    }

    private function selectedSemester(Request $request): ?string
    {
        $semester = trim((string) $request->string('semester', ''));
        if ($semester === '') {
            return null;
        }

        return preg_match('/^S([12])-(\d{4})$/', $semester) === 1 ? $semester : null;
    }

    private function selectedCustomerId(Request $request): ?int
    {
        $customerId = $request->integer('customer_id');

        return $customerId > 0 ? $customerId : null;
    }

    /**
     * @return array<int, array{label:string,value:int|float,type:string}>
     */
    private function receivableSummary(?string $selectedSemester, ?int $selectedCustomerId): array
    {
        $aggregate = SalesInvoice::query()
            ->where('balance', '>', 0)
            ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                $query->where('semester_period', $selectedSemester);
            })
            ->when($selectedCustomerId !== null, function ($query) use ($selectedCustomerId): void {
                $query->where('customer_id', $selectedCustomerId);
            })
            ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(balance), 0) as total_balance')
            ->first();

        return [
            [
                'label' => __('report.receivable_summary.total_unpaid_invoices'),
                'value' => (int) ($aggregate?->invoice_count ?? 0),
                'type' => 'number',
            ],
            [
                'label' => __('report.receivable_summary.total_outstanding'),
                'value' => (float) ($aggregate?->total_balance ?? 0),
                'type' => 'currency',
            ],
        ];
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    private function receivableFilters(?string $selectedSemester, ?int $selectedCustomerId): array
    {
        $customerName = __('report.all_customers');
        if ($selectedCustomerId !== null) {
            $customerName = Customer::query()
                ->whereKey($selectedCustomerId)
                ->value('name') ?? __('report.all_customers');
        }

        return [
            [
                'label' => __('report.filters.semester'),
                'value' => $selectedSemester ?? __('report.all_semesters'),
            ],
            [
                'label' => __('report.filters.customer'),
                'value' => $customerName,
            ],
        ];
    }

    private function semesterOptions(): array
    {
        $current = $this->currentSemesterPeriod();
        $previous = $this->previousSemesterPeriod($current);

        return SalesInvoice::query()
            ->whereNotNull('semester_period')
            ->where('semester_period', '!=', '')
            ->distinct()
            ->pluck('semester_period')
            ->merge(
                SalesReturn::query()
                    ->whereNotNull('semester_period')
                    ->where('semester_period', '!=', '')
                    ->distinct()
                    ->pluck('semester_period')
            )
            ->merge($this->configuredSemesterOptions())
            ->push($current)
            ->push($previous)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
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

    /**
     * @return array{start:string,end:string}|null
     */
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

    private function configuredSemesterOptions()
    {
        return collect(explode(',', (string) AppSetting::getValue('semester_period_options', '')))
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '');
    }
}
