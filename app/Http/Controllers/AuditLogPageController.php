<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\OrderNote;
use App\Models\ReceivablePayment;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogPageController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->resolveFilters($request);

        $logs = $this->buildFilteredLogsQuery($filters)
            ->latest('id')
            ->paginate(30)
            ->withQueryString();
        $viewMaps = $this->buildAuditViewMaps($logs->getCollection());

        return view('audit_logs.index', [
            'logs' => $logs,
            'search' => $filters['search'],
            'selectedModule' => $filters['selectedModule'],
            'selectedDateFrom' => $filters['dateFrom'],
            'selectedDateTo' => $filters['dateTo'],
            'subjectMap' => $viewMaps['subjectMap'],
            'subjectCodeMap' => $viewMaps['subjectCodeMap'],
            'descriptionMap' => $viewMaps['descriptionMap'],
            'codeLinkMap' => $viewMaps['codeLinkMap'],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->resolveFilters($request);
        $logs = $this->buildFilteredLogsQuery($filters)
            ->latest('id')
            ->limit(5000)
            ->get();

        $filename = 'audit-logs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($logs): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fwrite($handle, "sep=,\n");
            fputcsv($handle, [
                __('txn.date'),
                __('ui.user'),
                __('ui.actions'),
                __('ui.subject'),
                __('ui.description'),
                __('ui.ip'),
            ]);

            foreach ($logs as $log) {
                $subject = class_basename((string) $log->subject_type);
                if ($log->subject_id) {
                    $subject .= ' #'.$log->subject_id;
                }
                fputcsv($handle, [
                    (string) optional($log->created_at)->format('d-m-Y H:i:s'),
                    (string) ($log->user?->name ?? '-'),
                    (string) $log->action,
                    $subject,
                    (string) ($log->description ?: '-'),
                    (string) ($log->ip_address ?: '-'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{search:string,selectedModule:string,actionPrefix:?string,dateFrom:string,dateTo:string}
     */
    private function resolveFilters(Request $request): array
    {
        $search = trim((string) $request->string('search', ''));
        $module = trim((string) $request->string('module', ''));
        $dateFromInput = trim((string) $request->string('date_from', ''));
        $dateToInput = trim((string) $request->string('date_to', ''));

        $moduleMap = [
            'sales_invoice' => 'sales.invoice.',
            'sales_return' => 'sales.return.',
            'delivery_note' => 'delivery.note.',
            'order_note' => 'order.note.',
        ];
        $selectedModule = array_key_exists($module, $moduleMap) ? $module : '';
        $actionPrefix = $selectedModule !== '' ? $moduleMap[$selectedModule] : null;
        $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromInput) === 1 ? $dateFromInput : '';
        $dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToInput) === 1 ? $dateToInput : '';

        return [
            'search' => $search,
            'selectedModule' => $selectedModule,
            'actionPrefix' => $actionPrefix,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ];
    }

    /**
     * @param array{search:string,selectedModule:string,actionPrefix:?string,dateFrom:string,dateTo:string} $filters
     */
    private function buildFilteredLogsQuery(array $filters)
    {
        $search = $filters['search'];
        $actionPrefix = $filters['actionPrefix'];
        $dateFrom = $filters['dateFrom'];
        $dateTo = $filters['dateTo'];

        return AuditLog::query()
            ->with('user:id,name,email')
            ->when($actionPrefix !== null, function ($query) use ($actionPrefix): void {
                $query->where('action', 'like', $actionPrefix.'%');
            })
            ->when($dateFrom !== '', function ($query) use ($dateFrom): void {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo !== '', function ($query) use ($dateTo): void {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('action', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            });
    }

    /**
     * @param Collection<int, AuditLog> $logs
     * @return array{
     *   subjectMap: array<int,string>,
     *   subjectCodeMap: array<int,string>,
     *   descriptionMap: array<int,string>,
     *   codeLinkMap: array<string,string>
     * }
     */
    private function buildAuditViewMaps(Collection $logs): array
    {
        $subjectMap = [];
        $subjectCodeMap = [];
        $descriptionMap = [];
        $codeLinkMap = $this->resolveCodeLinks($logs);

        $idsByType = [];
        foreach ($logs as $log) {
            $type = (string) ($log->subject_type ?? '');
            $id = (int) ($log->subject_id ?? 0);
            if ($type === '' || $id <= 0) {
                continue;
            }
            $idsByType[$type][] = $id;
        }
        $idsByType = collect($idsByType)->map(fn (array $ids): array => array_values(array_unique($ids)))->all();

        $invoiceMap = [];
        if (isset($idsByType[SalesInvoice::class])) {
            $invoiceMap = SalesInvoice::query()
                ->with('customer:id,name')
                ->whereIn('id', $idsByType[SalesInvoice::class])
                ->get(['id', 'invoice_number', 'customer_id'])
                ->keyBy('id');
            foreach ($invoiceMap as $invoice) {
                if ($invoice->invoice_number) {
                    $codeLinkMap[strtoupper((string) $invoice->invoice_number)] = route('sales-invoices.show', $invoice);
                }
            }
        }

        $salesReturnMap = [];
        if (isset($idsByType[SalesReturn::class])) {
            $salesReturnMap = SalesReturn::query()
                ->with('customer:id,name')
                ->whereIn('id', $idsByType[SalesReturn::class])
                ->get(['id', 'return_number', 'customer_id'])
                ->keyBy('id');
            foreach ($salesReturnMap as $salesReturn) {
                if ($salesReturn->return_number) {
                    $codeLinkMap[strtoupper((string) $salesReturn->return_number)] = route('sales-returns.show', $salesReturn);
                }
            }
        }

        $deliveryMap = [];
        if (isset($idsByType[DeliveryNote::class])) {
            $deliveryMap = DeliveryNote::query()
                ->with('customer:id,name')
                ->whereIn('id', $idsByType[DeliveryNote::class])
                ->get(['id', 'note_number', 'customer_id', 'recipient_name'])
                ->keyBy('id');
            foreach ($deliveryMap as $deliveryNote) {
                if ($deliveryNote->note_number) {
                    $codeLinkMap[strtoupper((string) $deliveryNote->note_number)] = route('delivery-notes.show', $deliveryNote);
                }
            }
        }

        $orderMap = [];
        if (isset($idsByType[OrderNote::class])) {
            $orderMap = OrderNote::query()
                ->with('customer:id,name')
                ->whereIn('id', $idsByType[OrderNote::class])
                ->get(['id', 'note_number', 'customer_id', 'customer_name'])
                ->keyBy('id');
            foreach ($orderMap as $orderNote) {
                if ($orderNote->note_number) {
                    $codeLinkMap[strtoupper((string) $orderNote->note_number)] = route('order-notes.show', $orderNote);
                }
            }
        }

        $receivablePaymentMap = [];
        if (isset($idsByType[ReceivablePayment::class])) {
            $receivablePaymentMap = ReceivablePayment::query()
                ->with('customer:id,name')
                ->whereIn('id', $idsByType[ReceivablePayment::class])
                ->get(['id', 'payment_number', 'customer_id'])
                ->keyBy('id');
            foreach ($receivablePaymentMap as $payment) {
                if ($payment->payment_number) {
                    $codeLinkMap[strtoupper((string) $payment->payment_number)] = route('receivable-payments.show', $payment);
                }
            }
        }

        $customerMap = [];
        if (isset($idsByType[Customer::class])) {
            $customerMap = Customer::query()
                ->whereIn('id', $idsByType[Customer::class])
                ->get(['id', 'name'])
                ->keyBy('id');
        }

        foreach ($logs as $log) {
            $logId = (int) $log->id;
            $subjectMap[$logId] = class_basename((string) $log->subject_type) ?: '-';
            $subjectCodeMap[$logId] = '';
            $descriptionMap[$logId] = (string) ($log->description ?: '-');
            $subjectId = (int) ($log->subject_id ?? 0);

            if ($subjectId <= 0) {
                continue;
            }

            $subjectType = (string) ($log->subject_type ?? '');
            if ($subjectType === SalesInvoice::class && isset($invoiceMap[$subjectId])) {
                $invoice = $invoiceMap[$subjectId];
                $subjectMap[$logId] = (string) ($invoice->customer?->name ?? '-');
                $subjectCodeMap[$logId] = (string) ($invoice->invoice_number ?? '');
            } elseif ($subjectType === SalesReturn::class && isset($salesReturnMap[$subjectId])) {
                $salesReturn = $salesReturnMap[$subjectId];
                $subjectMap[$logId] = (string) ($salesReturn->customer?->name ?? '-');
                $subjectCodeMap[$logId] = (string) ($salesReturn->return_number ?? '');
            } elseif ($subjectType === DeliveryNote::class && isset($deliveryMap[$subjectId])) {
                $deliveryNote = $deliveryMap[$subjectId];
                $subjectMap[$logId] = (string) ($deliveryNote->customer?->name ?: $deliveryNote->recipient_name ?: '-');
                $subjectCodeMap[$logId] = (string) ($deliveryNote->note_number ?? '');
            } elseif ($subjectType === OrderNote::class && isset($orderMap[$subjectId])) {
                $orderNote = $orderMap[$subjectId];
                $subjectMap[$logId] = (string) ($orderNote->customer?->name ?: $orderNote->customer_name ?: '-');
                $subjectCodeMap[$logId] = (string) ($orderNote->note_number ?? '');
            } elseif ($subjectType === ReceivablePayment::class && isset($receivablePaymentMap[$subjectId])) {
                $payment = $receivablePaymentMap[$subjectId];
                $subjectMap[$logId] = (string) ($payment->customer?->name ?? '-');
                $subjectCodeMap[$logId] = (string) ($payment->payment_number ?? '');
            } elseif ($subjectType === Customer::class && isset($customerMap[$subjectId])) {
                $subjectMap[$logId] = (string) ($customerMap[$subjectId]->name ?? '-');
            }

            $shortDesc = $this->shortAuditDescription(
                (string) $log->action,
                $descriptionMap[$logId],
                $subjectCodeMap[$logId]
            );
            $descriptionMap[$logId] = $shortDesc;
        }

        return [
            'subjectMap' => $subjectMap,
            'subjectCodeMap' => $subjectCodeMap,
            'descriptionMap' => $descriptionMap,
            'codeLinkMap' => $codeLinkMap,
        ];
    }

    /**
     * @param Collection<int, AuditLog> $logs
     * @return array<string, string>
     */
    private function resolveCodeLinks(Collection $logs): array
    {
        $text = $logs
            ->map(fn (AuditLog $log): string => (string) ($log->description ?? ''))
            ->implode("\n");

        preg_match_all('/\b(?:INV|RET|RTN|RTR|SJ|PO|PYT|KWT)-\d{8}-\d{4}\b/i', $text, $matches);
        $codes = collect($matches[0] ?? [])->map(fn (string $code): string => strtoupper($code))->unique()->values();
        if ($codes->isEmpty()) {
            return [];
        }

        $map = [];
        $invoiceCodes = $codes->filter(fn (string $code): bool => str_starts_with($code, 'INV-'))->values();
        if ($invoiceCodes->isNotEmpty()) {
            $invoices = SalesInvoice::query()->whereIn('invoice_number', $invoiceCodes->all())->get(['id', 'invoice_number']);
            foreach ($invoices as $invoice) {
                $map[strtoupper((string) $invoice->invoice_number)] = route('sales-invoices.show', $invoice);
            }
        }

        $returnCodes = $codes->filter(fn (string $code): bool => str_starts_with($code, 'RET-') || str_starts_with($code, 'RTN-') || str_starts_with($code, 'RTR-'))->values();
        if ($returnCodes->isNotEmpty()) {
            $returns = SalesReturn::query()->whereIn('return_number', $returnCodes->all())->get(['id', 'return_number']);
            foreach ($returns as $salesReturn) {
                $map[strtoupper((string) $salesReturn->return_number)] = route('sales-returns.show', $salesReturn);
            }
        }

        $deliveryCodes = $codes->filter(fn (string $code): bool => str_starts_with($code, 'SJ-'))->values();
        if ($deliveryCodes->isNotEmpty()) {
            $deliveryNotes = DeliveryNote::query()->whereIn('note_number', $deliveryCodes->all())->get(['id', 'note_number']);
            foreach ($deliveryNotes as $deliveryNote) {
                $map[strtoupper((string) $deliveryNote->note_number)] = route('delivery-notes.show', $deliveryNote);
            }
        }

        $orderCodes = $codes->filter(fn (string $code): bool => str_starts_with($code, 'PO-'))->values();
        if ($orderCodes->isNotEmpty()) {
            $orderNotes = OrderNote::query()->whereIn('note_number', $orderCodes->all())->get(['id', 'note_number']);
            foreach ($orderNotes as $orderNote) {
                $map[strtoupper((string) $orderNote->note_number)] = route('order-notes.show', $orderNote);
            }
        }

        $paymentCodes = $codes->filter(fn (string $code): bool => str_starts_with($code, 'PYT-') || str_starts_with($code, 'KWT-'))->values();
        if ($paymentCodes->isNotEmpty()) {
            $payments = ReceivablePayment::query()->whereIn('payment_number', $paymentCodes->all())->get(['id', 'payment_number']);
            foreach ($payments as $payment) {
                $map[strtoupper((string) $payment->payment_number)] = route('receivable-payments.show', $payment);
            }
        }

        return $map;
    }

    private function shortAuditDescription(string $action, string $description, string $subjectCode): string
    {
        $normalized = trim($description);
        $code = trim($subjectCode);
        $actionLabel = match ($action) {
            'sales.invoice.admin_update' => __('ui.audit_action_sales_invoice_admin_update'),
            'sales.invoice.cancel' => __('ui.audit_action_sales_invoice_cancel'),
            'sales.return.admin_update' => __('ui.audit_action_sales_return_admin_update'),
            'sales.return.cancel' => __('ui.audit_action_sales_return_cancel'),
            'delivery.note.admin_update' => __('ui.audit_action_delivery_note_admin_update'),
            'delivery.note.cancel' => __('ui.audit_action_delivery_note_cancel'),
            'order.note.admin_update' => __('ui.audit_action_order_note_admin_update'),
            'order.note.cancel' => __('ui.audit_action_order_note_cancel'),
            default => '',
        };

        if ($actionLabel !== '') {
            if ($code !== '') {
                return $actionLabel.': '.$code;
            }
            if ($normalized !== '') {
                return $actionLabel.' - '.$normalized;
            }
            return $actionLabel;
        }

        return $normalized !== '' ? $normalized : '-';
    }
}
