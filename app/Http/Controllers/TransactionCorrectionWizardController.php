<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\DeliveryNote;
use App\Models\OrderNote;
use App\Models\OutgoingTransaction;
use App\Models\Product;
use App\Models\ReceivablePayment;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\SupplierPayment;
use App\Services\ApprovalWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

class TransactionCorrectionWizardController extends Controller
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const TYPE_MODEL_MAP = [
        'sales_invoice' => SalesInvoice::class,
        'sales_return' => SalesReturn::class,
        'delivery_note' => DeliveryNote::class,
        'order_note' => OrderNote::class,
        'outgoing_transaction' => OutgoingTransaction::class,
        'receivable_payment' => ReceivablePayment::class,
        'supplier_payment' => SupplierPayment::class,
    ];

    public function __construct(
        private readonly ApprovalWorkflowService $approvalWorkflowService
    ) {}

    public function create(Request $request): View
    {
        $type = trim((string) $request->string('type', 'sales_invoice'));
        if (! array_key_exists($type, self::TYPE_MODEL_MAP)) {
            $type = 'sales_invoice';
        }
        $id = $request->integer('id');

        $subject = $this->resolveSubject($type, $id);
        $subjectLabel = $this->subjectLabel($type, $subject);
        $initialPatchJson = $this->initialPatchJson($type, $subject);

        return view('transaction_corrections.create', [
            'type' => $type,
            'types' => array_keys(self::TYPE_MODEL_MAP),
            'subjectId' => $id > 0 ? $id : null,
            'subject' => $subject,
            'subjectLabel' => $subjectLabel,
            'initialPatchJson' => $initialPatchJson,
            'supportsAutoExecution' => in_array($type, ['sales_invoice', 'sales_return', 'delivery_note', 'order_note', 'receivable_payment'], true),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $allowedTypes = implode(',', array_keys(self::TYPE_MODEL_MAP));
        $data = $request->validate([
            'type' => ['required', 'in:'.$allowedTypes],
            'subject_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1200'],
            'requested_changes' => ['required', 'string', 'max:5000'],
            'requested_patch_json' => ['nullable', 'string', 'max:50000'],
        ]);

        $subject = $this->resolveSubject((string) $data['type'], (int) $data['subject_id']);
        abort_if($subject === null, 404);

        $supportsAutoExecution = in_array((string) $data['type'], ['sales_invoice', 'sales_return', 'delivery_note', 'order_note', 'receivable_payment'], true);
        if ($supportsAutoExecution && trim((string) ($data['requested_patch_json'] ?? '')) !== '') {
            $decoded = json_decode((string) $data['requested_patch_json'], true);
            if (! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'requested_patch_json' => 'Format JSON patch tidak valid.',
                ]);
            }
        }
        $patch = $this->parsePatchJson((string) ($data['requested_patch_json'] ?? ''));

        $approval = $this->approvalWorkflowService->create(
            module: 'transaction',
            action: 'correction',
            subject: $subject,
            payload: [
                'type' => (string) $data['type'],
                'requested_changes' => (string) $data['requested_changes'],
                'patch' => $patch,
            ],
            reason: (string) $data['reason'],
            request: $request
        );

        return redirect()
            ->route('approvals.index')
            ->with('success', 'Permintaan koreksi transaksi berhasil dibuat. Approval #'.$approval->id);
    }

    public function stockImpactPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:sales_invoice'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'requested_patch_json' => ['required', 'string', 'max:50000'],
        ]);

        $invoice = SalesInvoice::query()
            ->with('items')
            ->findOrFail((int) $data['subject_id']);
        $patch = $this->parsePatchJson((string) $data['requested_patch_json']);
        $rows = is_array($patch['items'] ?? null) ? $patch['items'] : [];

        $oldQtyByProduct = [];
        foreach ($invoice->items as $item) {
            $productId = (int) ($item->product_id ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $oldQtyByProduct[$productId] = ($oldQtyByProduct[$productId] ?? 0) + (int) $item->quantity;
        }

        $newQtyByProduct = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $newQtyByProduct[$productId] = ($newQtyByProduct[$productId] ?? 0) + max(0, (int) ($row['quantity'] ?? 0));
        }

        $productIds = collect(array_merge(array_keys($oldQtyByProduct), array_keys($newQtyByProduct)))
            ->unique()
            ->values();
        $products = Product::query()
            ->whereIn('id', $productIds->all())
            ->get(['id', 'name'])
            ->keyBy('id');

        $impactRows = [];
        foreach ($productIds as $productId) {
            $oldQty = (int) ($oldQtyByProduct[(int) $productId] ?? 0);
            $newQty = (int) ($newQtyByProduct[(int) $productId] ?? 0);
            $deltaStock = $oldQty - $newQty;
            $impactRows[] = [
                'product_id' => (int) $productId,
                'product_name' => (string) ($products->get((int) $productId)?->name ?? ('#'.$productId)),
                'old_qty' => $oldQty,
                'new_qty' => $newQty,
                'delta_stock' => $deltaStock,
                'delta_label' => $deltaStock > 0 ? '+'.$deltaStock : (string) $deltaStock,
            ];
        }

        return response()->json([
            'ok' => true,
            'rows' => $impactRows,
        ]);
    }

    private function resolveSubject(string $type, int $id): ?Model
    {
        if ($id <= 0) {
            return null;
        }

        $modelClass = self::TYPE_MODEL_MAP[$type] ?? null;
        if ($modelClass === null) {
            return null;
        }

        $query = $modelClass::query();
        if ($type === 'sales_invoice') {
            $query->with(['customer:id,name', 'items']);
        } elseif (in_array($type, ['sales_return', 'delivery_note', 'order_note'], true)) {
            $query->with('items');
        }

        return $query->find($id);
    }

    private function subjectLabel(string $type, ?Model $subject): string
    {
        if ($subject === null) {
            return '-';
        }

        return match ($type) {
            'sales_invoice' => (string) ($subject->invoice_number ?? '#'.$subject->getKey())
                .' - '.(string) ($subject->customer?->name ?? '-'),
            'sales_return' => (string) ($subject->return_number ?? '#'.$subject->getKey()),
            'delivery_note', 'order_note' => (string) ($subject->note_number ?? '#'.$subject->getKey()),
            'outgoing_transaction' => (string) ($subject->transaction_number ?? '#'.$subject->getKey()),
            'receivable_payment', 'supplier_payment' => (string) ($subject->payment_number ?? '#'.$subject->getKey()),
            default => '#'.$subject->getKey(),
        };
    }

    private function initialPatchJson(string $type, ?Model $subject): string
    {
        $patch = match ($type) {
            'sales_invoice' => $subject instanceof SalesInvoice ? [
                'invoice_date' => optional($subject->invoice_date)->format('Y-m-d'),
                'due_date' => optional($subject->due_date)->format('Y-m-d'),
                'semester_period' => (string) ($subject->semester_period ?? ''),
                'notes' => (string) ($subject->notes ?? ''),
                'items' => $subject->items->map(fn ($item): array => [
                    'product_id' => (int) $item->product_id,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (int) round((float) $item->unit_price),
                    'discount' => (int) round(((float) $item->discount > 0 && (float) $item->quantity > 0 && (float) $item->unit_price > 0)
                        ? ((float) $item->discount / ((float) $item->quantity * (float) $item->unit_price) * 100)
                        : 0),
                ])->values()->all(),
            ] : [],
            'sales_return' => $subject instanceof SalesReturn ? [
                'return_date' => optional($subject->return_date)->format('Y-m-d'),
                'semester_period' => (string) ($subject->semester_period ?? ''),
                'reason' => (string) ($subject->reason ?? ''),
                'items' => $subject->items->map(fn ($item): array => [
                    'product_id' => (int) $item->product_id,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (int) round((float) $item->unit_price),
                ])->values()->all(),
            ] : [],
            'delivery_note' => $subject instanceof DeliveryNote ? [
                'note_date' => optional($subject->note_date)->format('Y-m-d'),
                'recipient_name' => (string) ($subject->recipient_name ?? ''),
                'recipient_phone' => (string) ($subject->recipient_phone ?? ''),
                'city' => (string) ($subject->city ?? ''),
                'address' => (string) ($subject->address ?? ''),
                'notes' => (string) ($subject->notes ?? ''),
                'items' => $subject->items->map(fn ($item): array => [
                    'product_id' => (int) ($item->product_id ?? 0),
                    'product_name' => (string) ($item->product_name ?? ''),
                    'unit' => (string) ($item->unit ?? ''),
                    'quantity' => (int) ($item->quantity ?? 0),
                    'unit_price' => (int) round((float) ($item->unit_price ?? 0)),
                    'notes' => (string) ($item->notes ?? ''),
                ])->values()->all(),
            ] : [],
            'order_note' => $subject instanceof OrderNote ? [
                'note_date' => optional($subject->note_date)->format('Y-m-d'),
                'customer_name' => (string) ($subject->customer_name ?? ''),
                'customer_phone' => (string) ($subject->customer_phone ?? ''),
                'city' => (string) ($subject->city ?? ''),
                'notes' => (string) ($subject->notes ?? ''),
                'items' => $subject->items->map(fn ($item): array => [
                    'product_id' => (int) ($item->product_id ?? 0),
                    'product_name' => (string) ($item->product_name ?? ''),
                    'quantity' => (int) ($item->quantity ?? 0),
                    'notes' => (string) ($item->notes ?? ''),
                ])->values()->all(),
            ] : [],
            'receivable_payment' => $subject instanceof ReceivablePayment ? [
                'payment_date' => optional($subject->payment_date)->format('Y-m-d'),
                'customer_address' => (string) ($subject->customer_address ?? ''),
                'customer_signature' => (string) ($subject->customer_signature ?? ''),
                'user_signature' => (string) ($subject->user_signature ?? ''),
                'notes' => (string) ($subject->notes ?? ''),
            ] : [],
            default => [],
        };
        if ($patch === []) {
            return '';
        }

        $encoded = json_encode($patch, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePatchJson(string $json): array
    {
        $trimmed = trim($json);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
