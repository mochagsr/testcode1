<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Support\SemesterBookService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SemesterTransactionPageController extends Controller
{
    public function __construct(
        private readonly SemesterBookService $semesterBookService
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));
        $type = trim((string) $request->string('type', ''));
        $semester = trim((string) $request->string('semester', ''));
        $selectedType = in_array($type, ['all', 'sales_invoice', 'sales_return', 'delivery_note', 'order_note', 'outgoing_transaction', 'receivable_payment'], true)
            ? $type
            : 'all';

        $semesterOptions = $this->semesterOptions();
        $currentSemester = $this->semesterBookService->currentSemester();
        $selectedSemester = $this->semesterBookService->normalizeSemester($semester) ?? '';
        if ($selectedSemester === '') {
            $selectedSemester = $semesterOptions->contains($currentSemester)
                ? $currentSemester
                : (string) ($semesterOptions->first() ?? $currentSemester);
        }
        if (! $semesterOptions->contains($selectedSemester)) {
            $selectedSemester = (string) ($semesterOptions->first() ?? $currentSemester);
        }

        $selectedRange = $this->semesterRange($selectedSemester);
        $transactions = $this->buildQuery($selectedSemester, $selectedRange['start'], $selectedRange['end'])
            ->when($selectedType !== 'all', function ($query) use ($selectedType): void {
                $query->where('tx_type', $selectedType);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('tx_number', 'like', "%{$search}%")
                        ->orWhere('party_name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('tx_date')
            ->orderByDesc('tx_id')
            ->paginate(20)
            ->withQueryString();

        $transactions->getCollection()->transform(function ($row) {
            $row->detail_url = $this->detailUrl((string) $row->tx_type, (int) $row->tx_id);
            $row->type_label = $this->typeLabel((string) $row->tx_type);

            return $row;
        });

        return view('semester_transactions.index', [
            'transactions' => $transactions,
            'semesterOptions' => $semesterOptions,
            'selectedSemester' => $selectedSemester,
            'selectedType' => $selectedType,
            'search' => $search,
        ]);
    }

    private function buildQuery(string $semester, Carbon $start, Carbon $end): \Illuminate\Database\Query\Builder
    {
        $invoiceQuery = DB::table('sales_invoices as si')
            ->leftJoin('customers as c', 'c.id', '=', 'si.customer_id')
            ->selectRaw("'sales_invoice' as tx_type, si.id as tx_id, si.invoice_date as tx_date, si.invoice_number as tx_number, COALESCE(c.name, '-') as party_name, COALESCE(c.city, '-') as city, si.total as amount, si.is_canceled as is_canceled")
            ->where('si.semester_period', $semester);

        $returnQuery = DB::table('sales_returns as sr')
            ->leftJoin('customers as c', 'c.id', '=', 'sr.customer_id')
            ->selectRaw("'sales_return' as tx_type, sr.id as tx_id, sr.return_date as tx_date, sr.return_number as tx_number, COALESCE(c.name, '-') as party_name, COALESCE(c.city, '-') as city, sr.total as amount, sr.is_canceled as is_canceled")
            ->where('sr.semester_period', $semester);

        $deliveryQuery = DB::table('delivery_notes as dn')
            ->selectRaw("'delivery_note' as tx_type, dn.id as tx_id, dn.note_date as tx_date, dn.note_number as tx_number, COALESCE(dn.recipient_name, '-') as party_name, COALESCE(dn.city, '-') as city, NULL as amount, dn.is_canceled as is_canceled")
            ->whereBetween('dn.note_date', [$start, $end]);

        $orderQuery = DB::table('order_notes as onote')
            ->selectRaw("'order_note' as tx_type, onote.id as tx_id, onote.note_date as tx_date, onote.note_number as tx_number, COALESCE(onote.customer_name, '-') as party_name, COALESCE(onote.city, '-') as city, NULL as amount, onote.is_canceled as is_canceled")
            ->whereBetween('onote.note_date', [$start, $end]);

        $outgoingQuery = DB::table('outgoing_transactions as ot')
            ->leftJoin('suppliers as s', 's.id', '=', 'ot.supplier_id')
            ->selectRaw("'outgoing_transaction' as tx_type, ot.id as tx_id, ot.transaction_date as tx_date, ot.transaction_number as tx_number, COALESCE(s.name, s.company_name, '-') as party_name, '-' as city, ot.total as amount, 0 as is_canceled")
            ->where('ot.semester_period', $semester);

        $receivablePaymentQuery = DB::table('receivable_payments as rp')
            ->leftJoin('customers as c', 'c.id', '=', 'rp.customer_id')
            ->selectRaw("'receivable_payment' as tx_type, rp.id as tx_id, rp.payment_date as tx_date, rp.payment_number as tx_number, COALESCE(c.name, '-') as party_name, COALESCE(c.city, '-') as city, rp.amount as amount, rp.is_canceled as is_canceled")
            ->whereBetween('rp.payment_date', [$start, $end]);

        $union = $invoiceQuery
            ->unionAll($returnQuery)
            ->unionAll($deliveryQuery)
            ->unionAll($orderQuery)
            ->unionAll($outgoingQuery)
            ->unionAll($receivablePaymentQuery);

        return DB::query()->fromSub($union, 'semester_transactions');
    }

    private function detailUrl(string $type, int $id): string
    {
        return match ($type) {
            'sales_invoice' => route('sales-invoices.show', $id),
            'sales_return' => route('sales-returns.show', $id),
            'delivery_note' => route('delivery-notes.show', $id),
            'order_note' => route('order-notes.show', $id),
            'outgoing_transaction' => route('outgoing-transactions.show', $id),
            'receivable_payment' => route('receivable-payments.show', $id),
            default => '#',
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'sales_invoice' => __('menu.sales_invoices'),
            'sales_return' => __('menu.sales_returns'),
            'delivery_note' => __('menu.delivery_notes'),
            'order_note' => __('menu.order_notes'),
            'outgoing_transaction' => __('menu.outgoing_transactions'),
            'receivable_payment' => __('menu.receivable_payments'),
            default => $type,
        };
    }

    /**
     * @return array{start:Carbon,end:Carbon}
     */
    private function semesterRange(string $semester): array
    {
        if (preg_match('/^S([12])-(\d{2})(\d{2})$/', $semester, $matches) === 1) {
            $half = (int) $matches[1];
            $startYear = 2000 + (int) $matches[2];
            $endYear = 2000 + (int) $matches[3];
            if ($half === 1) {
                return [
                    'start' => Carbon::create($startYear, 5, 1)->startOfDay(),
                    'end' => Carbon::create($startYear, 10, 31)->endOfDay(),
                ];
            }

            return [
                'start' => Carbon::create($startYear, 11, 1)->startOfDay(),
                'end' => Carbon::create($endYear, 4, 30)->endOfDay(),
            ];
        }

        return [
            'start' => now()->startOfYear(),
            'end' => now()->endOfYear(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function semesterOptions(): \Illuminate\Support\Collection
    {
        $configured = collect(preg_split('/[\r\n,]+/', (string) AppSetting::getValue('semester_period_options', '')) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->map(fn (string $item): ?string => $this->semesterBookService->normalizeSemester($item))
            ->filter(fn (?string $item): bool => $item !== null);

        $invoiceSemesters = DB::table('sales_invoices')
            ->whereNotNull('semester_period')
            ->where('semester_period', '!=', '')
            ->distinct()
            ->pluck('semester_period')
            ->map(fn (string $item): ?string => $this->semesterBookService->normalizeSemester($item))
            ->filter(fn (?string $item): bool => $item !== null);

        $returnSemesters = DB::table('sales_returns')
            ->whereNotNull('semester_period')
            ->where('semester_period', '!=', '')
            ->distinct()
            ->pluck('semester_period')
            ->map(fn (string $item): ?string => $this->semesterBookService->normalizeSemester($item))
            ->filter(fn (?string $item): bool => $item !== null);

        $outgoingSemesters = DB::table('outgoing_transactions')
            ->whereNotNull('semester_period')
            ->where('semester_period', '!=', '')
            ->distinct()
            ->pluck('semester_period')
            ->map(fn (string $item): ?string => $this->semesterBookService->normalizeSemester($item))
            ->filter(fn (?string $item): bool => $item !== null);

        return $configured
            ->merge($invoiceSemesters)
            ->merge($returnSemesters)
            ->merge($outgoingSemesters)
            ->merge($this->semesterBookService->closedSemesters())
            ->push($this->semesterBookService->currentSemester())
            ->push($this->semesterBookService->previousSemester($this->semesterBookService->currentSemester()))
            ->unique()
            ->sortDesc()
            ->values();
    }
}
