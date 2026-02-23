<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\OrderNote;
use App\Models\ReceivablePayment;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\SupplierPayment;
use App\Support\ExcelExportStyler;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogPageController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->resolveFilters($request);

        $logs = $this->buildFilteredLogsQuery($filters)
            ->latest('id')
            ->paginate((int) config('pagination.audit_per_page', 50))
            ->withQueryString();
        $viewMaps = $this->buildAuditViewMaps($logs->getCollection());

        return view('audit_logs.index', [
            'logs' => $logs,
            'search' => $filters['search'],
            'selectedDocumentCode' => $filters['documentCode'],
            'selectedModule' => $filters['selectedModule'],
            'selectedDateFrom' => $filters['dateFrom'],
            'selectedDateTo' => $filters['dateTo'],
            'subjectMap' => $viewMaps['subjectMap'],
            'subjectCodeMap' => $viewMaps['subjectCodeMap'],
            'descriptionMap' => $viewMaps['descriptionMap'],
            'beforeAfterMap' => $viewMaps['beforeAfterMap'],
            'codeLinkMap' => $viewMaps['codeLinkMap'],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->resolveFilters($request);
        $logQuery = $this->buildFilteredLogsQuery($filters)
            ->latest('id')
            ->limit(5000);
        $logCount = (clone $logQuery)->count();

        $filename = 'audit-logs-'.$this->nowWib()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($logQuery, $logCount): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Audit Logs');
            $rows = [[
                __('txn.date'),
                __('ui.user'),
                __('ui.actions'),
                __('ui.subject'),
                __('ui.description'),
                __('ui.ip'),
            ]];
            $sheet->fromArray($rows, null, 'A1');

            $row = 2;
            foreach ($logQuery->lazy(500) as $log) {
                $subject = class_basename((string) $log->subject_type);
                if ($log->subject_id) {
                    $subject .= ' #'.$log->subject_id;
                }

                $sheet->setCellValue('A'.$row, (string) optional($log->created_at)->timezone('Asia/Jakarta')->format('d-m-Y H:i:s'));
                $sheet->setCellValue('B'.$row, (string) ($log->user?->name ?? '-'));
                $sheet->setCellValue('C'.$row, (string) $log->action);
                $sheet->setCellValue('D'.$row, $subject);
                $sheet->setCellValue('E'.$row, (string) ($log->description ?: '-'));
                $sheet->setCellValue('F'.$row, (string) ($log->ip_address ?: '-'));
                $row++;
            }

            ExcelExportStyler::styleTable($sheet, 1, 6, $logCount, true);
            if ($logCount > 0) {
                $sheet->getStyle('A2:A'.(1 + $logCount))->getNumberFormat()->setFormatCode('@');
                $sheet->getStyle('F2:F'.(1 + $logCount))->getNumberFormat()->setFormatCode('@');
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
     * @return array{search:string,documentCode:string,selectedModule:string,actionPrefix:?string,dateFrom:string,dateTo:string}
     */
    private function resolveFilters(Request $request): array
    {
        $search = trim((string) $request->string('search', ''));
        $documentCode = strtoupper(trim((string) $request->string('doc_code', '')));
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
            'documentCode' => $documentCode,
            'selectedModule' => $selectedModule,
            'actionPrefix' => $actionPrefix,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ];
    }

    /**
     * @param array{search:string,documentCode:string,selectedModule:string,actionPrefix:?string,dateFrom:string,dateTo:string} $filters
     */
    private function buildFilteredLogsQuery(array $filters)
    {
        $search = $filters['search'];
        $documentCode = $filters['documentCode'];
        $actionPrefix = $filters['actionPrefix'];
        $dateFrom = $filters['dateFrom'];
        $dateTo = $filters['dateTo'];
        $dateFromStart = $dateFrom !== '' ? Carbon::parse($dateFrom)->startOfDay()->toDateTimeString() : null;
        $dateToEnd = $dateTo !== '' ? Carbon::parse($dateTo)->endOfDay()->toDateTimeString() : null;

        return AuditLog::query()
            ->select([
                'id',
                'user_id',
                'action',
                'subject_type',
                'subject_id',
                'description',
                'before_data',
                'after_data',
                'request_id',
                'ip_address',
                'created_at',
            ])
            ->with('user:id,name,email')
            ->when($actionPrefix !== null, function ($query) use ($actionPrefix): void {
                $query->where('action', 'like', $actionPrefix.'%');
            })
            ->when($dateFromStart !== null, function ($query) use ($dateFromStart): void {
                $query->where('created_at', '>=', $dateFromStart);
            })
            ->when($dateToEnd !== null, function ($query) use ($dateToEnd): void {
                $query->where('created_at', '<=', $dateToEnd);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('action', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('request_id', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($documentCode !== '', function ($query) use ($documentCode): void {
                $query->where(function ($subQuery) use ($documentCode): void {
                    $subQuery->where('description', 'like', "%{$documentCode}%")
                        ->orWhere(function ($documentQuery) use ($documentCode): void {
                            if (str_starts_with($documentCode, 'INV-')) {
                                $documentQuery->where('subject_type', SalesInvoice::class)
                                    ->whereIn('subject_id', SalesInvoice::query()
                                        ->where('invoice_number', $documentCode)
                                        ->select('id'));
                                return;
                            }
                            if (str_starts_with($documentCode, 'RTR-') || str_starts_with($documentCode, 'RET-') || str_starts_with($documentCode, 'RTN-')) {
                                $documentQuery->where('subject_type', SalesReturn::class)
                                    ->whereIn('subject_id', SalesReturn::query()
                                        ->where('return_number', $documentCode)
                                        ->select('id'));
                                return;
                            }
                            if (str_starts_with($documentCode, 'SJ-')) {
                                $documentQuery->where('subject_type', DeliveryNote::class)
                                    ->whereIn('subject_id', DeliveryNote::query()
                                        ->where('note_number', $documentCode)
                                        ->select('id'));
                                return;
                            }
                            if (str_starts_with($documentCode, 'PO-')) {
                                $documentQuery->where('subject_type', OrderNote::class)
                                    ->whereIn('subject_id', OrderNote::query()
                                        ->where('note_number', $documentCode)
                                        ->select('id'));
                                return;
                            }
                            if (str_starts_with($documentCode, 'KWT-') || str_starts_with($documentCode, 'PYT-')) {
                                $documentQuery->where('subject_type', ReceivablePayment::class)
                                    ->whereIn('subject_id', ReceivablePayment::query()
                                        ->where('payment_number', $documentCode)
                                        ->select('id'));
                                return;
                            }
                            if (str_starts_with($documentCode, 'SPY-')) {
                                $documentQuery->where('subject_type', SupplierPayment::class)
                                    ->whereIn('subject_id', SupplierPayment::query()
                                        ->where('payment_number', $documentCode)
                                        ->select('id'));
                            }
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
     *   beforeAfterMap: array<int,array{before:string,after:string}>,
     *   codeLinkMap: array<string,string>
     * }
     */
    private function buildAuditViewMaps(Collection $logs): array
    {
        $subjectMap = [];
        $subjectCodeMap = [];
        $descriptionMap = [];
        $beforeAfterMap = [];
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
                ->whereIn('id', $idsByType[ReceivablePayment::class])
                ->get(['id', 'payment_number', 'customer_id'])
                ->keyBy('id');
            foreach ($receivablePaymentMap as $payment) {
                if ($payment->payment_number) {
                    $codeLinkMap[strtoupper((string) $payment->payment_number)] = route('receivable-payments.show', $payment);
                }
            }
        }

        $supplierPaymentMap = [];
        if (isset($idsByType[SupplierPayment::class])) {
            $supplierPaymentMap = SupplierPayment::query()
                ->whereIn('id', $idsByType[SupplierPayment::class])
                ->get(['id', 'payment_number', 'supplier_id'])
                ->keyBy('id');
            foreach ($supplierPaymentMap as $payment) {
                if ($payment->payment_number) {
                    $codeLinkMap[strtoupper((string) $payment->payment_number)] = route('supplier-payables.show-payment', $payment);
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

        $relatedCustomerIds = collect()
            ->merge(collect($invoiceMap)->pluck('customer_id'))
            ->merge(collect($salesReturnMap)->pluck('customer_id'))
            ->merge(collect($deliveryMap)->pluck('customer_id'))
            ->merge(collect($orderMap)->pluck('customer_id'))
            ->merge(collect($receivablePaymentMap)->pluck('customer_id'))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $relatedCustomerNameMap = $relatedCustomerIds->isNotEmpty()
            ? Customer::query()
                ->whereIn('id', $relatedCustomerIds->all())
                ->pluck('name', 'id')
                ->all()
            : [];

        foreach ($logs as $log) {
            $logId = (int) $log->id;
            $subjectMap[$logId] = class_basename((string) $log->subject_type) ?: '-';
            $subjectCodeMap[$logId] = '';
            $descriptionMap[$logId] = (string) ($log->description ?: '-');
            $beforeAfterMap[$logId] = ['before' => '-', 'after' => '-'];
            $subjectId = (int) ($log->subject_id ?? 0);

            if ($subjectId <= 0) {
                continue;
            }

            $subjectType = (string) ($log->subject_type ?? '');
            if ($subjectType === SalesInvoice::class && isset($invoiceMap[$subjectId])) {
                $invoice = $invoiceMap[$subjectId];
                $subjectMap[$logId] = (string) ($relatedCustomerNameMap[(int) ($invoice->customer_id ?? 0)] ?? '-');
                $subjectCodeMap[$logId] = (string) ($invoice->invoice_number ?? '');
            } elseif ($subjectType === SalesReturn::class && isset($salesReturnMap[$subjectId])) {
                $salesReturn = $salesReturnMap[$subjectId];
                $subjectMap[$logId] = (string) ($relatedCustomerNameMap[(int) ($salesReturn->customer_id ?? 0)] ?? '-');
                $subjectCodeMap[$logId] = (string) ($salesReturn->return_number ?? '');
            } elseif ($subjectType === DeliveryNote::class && isset($deliveryMap[$subjectId])) {
                $deliveryNote = $deliveryMap[$subjectId];
                $subjectMap[$logId] = (string) ($relatedCustomerNameMap[(int) ($deliveryNote->customer_id ?? 0)] ?: $deliveryNote->recipient_name ?: '-');
                $subjectCodeMap[$logId] = (string) ($deliveryNote->note_number ?? '');
            } elseif ($subjectType === OrderNote::class && isset($orderMap[$subjectId])) {
                $orderNote = $orderMap[$subjectId];
                $subjectMap[$logId] = (string) ($relatedCustomerNameMap[(int) ($orderNote->customer_id ?? 0)] ?: $orderNote->customer_name ?: '-');
                $subjectCodeMap[$logId] = (string) ($orderNote->note_number ?? '');
            } elseif ($subjectType === ReceivablePayment::class && isset($receivablePaymentMap[$subjectId])) {
                $payment = $receivablePaymentMap[$subjectId];
                $subjectMap[$logId] = (string) ($relatedCustomerNameMap[(int) ($payment->customer_id ?? 0)] ?? '-');
                $subjectCodeMap[$logId] = (string) ($payment->payment_number ?? '');
            } elseif ($subjectType === Customer::class && isset($customerMap[$subjectId])) {
                $subjectMap[$logId] = (string) ($customerMap[$subjectId]->name ?? '-');
            } elseif ($subjectType === SupplierPayment::class && isset($supplierPaymentMap[$subjectId])) {
                $subjectMap[$logId] = 'Supplier Payment';
                $subjectCodeMap[$logId] = (string) ($supplierPaymentMap[$subjectId]->payment_number ?? '');
            }

            $shortDesc = $this->shortAuditDescription(
                (string) $log->action,
                $descriptionMap[$logId],
                $subjectCodeMap[$logId]
            );
            $descriptionMap[$logId] = $shortDesc;
            $beforeAfterMap[$logId] = [
                'before' => is_array($log->before_data) ? $this->formatAuditPayloadForDisplay($log->before_data) : '-',
                'after' => is_array($log->after_data) ? $this->formatAuditPayloadForDisplay($log->after_data) : '-',
            ];
        }

        return [
            'subjectMap' => $subjectMap,
            'subjectCodeMap' => $subjectCodeMap,
            'descriptionMap' => $descriptionMap,
            'beforeAfterMap' => $beforeAfterMap,
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

    /**
     * @param array<string, mixed>|array<int, mixed> $payload
     */
    private function formatAuditPayloadForDisplay(array $payload): string
    {
        if ($payload === []) {
            return '-';
        }

        $translated = $this->translateAuditPayload($payload);
        $lines = [];
        $this->flattenAuditPayloadLines($translated, $lines);

        return $lines !== [] ? implode(PHP_EOL, $lines) : '-';
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function translateAuditPayload(mixed $value, ?string $fieldKey = null): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(fn($row) => $this->translateAuditPayload($row), $value);
            }

            $translated = [];
            foreach ($value as $key => $rowValue) {
                $keyString = (string) $key;
                $translated[$this->translateAuditFieldLabel($keyString)] = $this->translateAuditPayload($rowValue, $keyString);
            }

            return $translated;
        }

        if (is_bool($value)) {
            return $value ? __('ui.yes') : __('ui.no');
        }

        if ($value === null) {
            return '-';
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return '-';
            }

            if ($fieldKey !== null) {
                $fieldNormalized = strtolower($fieldKey);
                $enumMap = $this->auditFieldValueMap($fieldNormalized);
                $enumKey = strtolower($trimmed);
                if (isset($enumMap[$enumKey])) {
                    return $enumMap[$enumKey];
                }
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
                return Carbon::parse($trimmed)->format('d-m-Y');
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $trimmed) === 1) {
                return Carbon::parse($trimmed)->format('d-m-Y H:i:s');
            }

            return $trimmed;
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @param array<int, string> $lines
     */
    private function flattenAuditPayloadLines(mixed $value, array &$lines, string $indent = ''): void
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                foreach ($value as $index => $row) {
                    $number = (int) $index + 1;
                    if (is_array($row)) {
                        $lines[] = $indent.'- #'.$number;
                        $this->flattenAuditPayloadLines($row, $lines, $indent.'  ');
                        continue;
                    }

                    $lines[] = $indent.'- '.$this->stringifyAuditValue($row);
                }

                return;
            }

            foreach ($value as $key => $row) {
                $keyText = (string) $key;
                if (is_array($row)) {
                    $lines[] = $indent.$keyText.':';
                    $this->flattenAuditPayloadLines($row, $lines, $indent.'  ');
                    continue;
                }

                $lines[] = $indent.$keyText.': '.$this->stringifyAuditValue($row);
            }

            return;
        }

        $lines[] = $indent.$this->stringifyAuditValue($value);
    }

    /**
     * @param mixed $value
     */
    private function stringifyAuditValue(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }
        if (is_bool($value)) {
            return $value ? __('ui.yes') : __('ui.no');
        }
        if (is_scalar($value)) {
            $text = trim((string) $value);
            return $text !== '' ? $text : '-';
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) && $encoded !== '' ? $encoded : '-';
    }

    private function translateAuditFieldLabel(string $field): string
    {
        $key = strtolower(trim($field));
        $map = __('ui.audit_field_labels');
        if (is_array($map) && isset($map[$key]) && is_string($map[$key])) {
            return (string) $map[$key];
        }

        return ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * @return array<string, string>
     */
    private function auditFieldValueMap(string $field): array
    {
        return match ($field) {
            'payment_method' => [
                'cash' => __('txn.cash'),
                'credit' => __('txn.credit'),
            ],
            'payment_status' => [
                'paid' => __('txn.paid'),
                'partial' => 'Partial',
                'unpaid' => __('txn.unpaid'),
            ],
            'mutation_type' => [
                'in' => __('ui.stock_mutation_type_in'),
                'out' => __('ui.stock_mutation_type_out'),
            ],
            default => [],
        };
    }

    private function nowWib(): Carbon
    {
        return now('Asia/Jakarta');
    }
}
