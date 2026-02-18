<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\OrderNote;
use App\Models\ReceivablePayment;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\OutgoingTransaction;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Support\SemesterBookService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSemesterOpen
{
    public function __construct(
        private readonly SemesterBookService $semesterBookService
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $semester = $this->resolveSemester($request);
        if ($semester !== null && $this->semesterBookService->isClosed($semester)) {
            return back()->withErrors([
                'semester' => __('ui.semester_closed_error', ['semester' => $semester]),
            ])->withInput();
        }
        $customerId = $this->resolveCustomerId($request);
        $supplierId = $this->resolveSupplierId($request);
        $isAdmin = (($request->user()?->role ?? '') === 'admin');
        if (! $isAdmin && $semester !== null && $this->semesterBookService->isCustomerLocked($customerId, $semester)) {
            return back()->withErrors([
                'semester' => __('ui.customer_semester_closed_error', ['semester' => $semester]),
            ])->withInput()->with('error_popup', __('ui.contact_admin_for_locked_customer_semester'));
        }
        if (! $isAdmin && $semester !== null && $this->semesterBookService->isSupplierClosed($supplierId, $semester)) {
            return back()->withErrors([
                'semester_period' => __('txn.supplier_semester_closed_error', ['semester' => $semester]),
            ])->withInput()->with('error_popup', __('ui.contact_admin_for_locked_customer_semester'));
        }
        if ($semester !== null && ! $this->semesterBookService->isActive($semester)) {
            return back()->withErrors([
                'semester' => __('ui.semester_inactive_error', ['semester' => $semester]),
            ])->withInput();
        }

        return $next($request);
    }

    private function resolveSemester(Request $request): ?string
    {
        $routeName = (string) optional($request->route())->getName();

        return match ($routeName) {
            'sales-invoices.store' => $this->semesterBookService->normalizeSemester((string) $request->input('semester_period'))
                ?? $this->semesterBookService->semesterFromDate((string) $request->input('invoice_date')),
            'sales-invoices.admin-update', 'sales-invoices.cancel' => $this->semesterBookService->normalizeSemester((string) optional($request->route('salesInvoice'))->semester_period),
            'sales-returns.store' => $this->semesterBookService->normalizeSemester((string) $request->input('semester_period'))
                ?? $this->semesterBookService->semesterFromDate((string) $request->input('return_date')),
            'sales-returns.admin-update', 'sales-returns.cancel' => $this->semesterBookService->normalizeSemester((string) optional($request->route('salesReturn'))->semester_period),
            'delivery-notes.store', 'order-notes.store' => $this->semesterBookService->semesterFromDate((string) $request->input('note_date')),
            'delivery-notes.admin-update', 'delivery-notes.cancel' => $this->semesterBookService->semesterFromDate((string) optional($request->route('deliveryNote'))->note_date),
            'order-notes.admin-update', 'order-notes.cancel' => $this->semesterBookService->semesterFromDate((string) optional($request->route('orderNote'))->note_date),
            'receivable-payments.store' => $this->semesterBookService->semesterFromDate((string) $request->input('payment_date')),
            'receivable-payments.admin-update', 'receivable-payments.cancel' => $this->semesterBookService->semesterFromDate((string) optional($request->route('receivablePayment'))->payment_date),
            'receivables.customer-writeoff', 'receivables.customer-discount' => $this->semesterBookService->normalizeSemester((string) $request->input('semester'))
                ?? $this->semesterBookService->semesterFromDate((string) $request->input('payment_date')),
            default => $this->semesterBookService->normalizeSemester((string) $request->input('semester_period'))
                ?? $this->semesterBookService->normalizeSemester((string) $request->input('semester'))
                ?? $this->semesterBookService->semesterFromDate((string) $request->input('transaction_date'))
                ?? $this->semesterBookService->semesterFromDate((string) $request->input('invoice_date'))
                ?? $this->semesterBookService->semesterFromDate((string) $request->input('return_date'))
                ?? $this->semesterBookService->semesterFromDate((string) $request->input('note_date'))
                ?? $this->semesterBookService->semesterFromDate((string) $request->input('payment_date')),
        };
    }

    private function resolveCustomerId(Request $request): ?int
    {
        $routeName = (string) optional($request->route())->getName();

        return match ($routeName) {
            'sales-invoices.store', 'sales-returns.store', 'receivable-payments.store' => $this->normalizeCustomerId($request->input('customer_id')),
            'sales-invoices.admin-update', 'sales-invoices.cancel' => $this->normalizeCustomerId(optional($request->route('salesInvoice'))->customer_id),
            'sales-returns.admin-update', 'sales-returns.cancel' => $this->normalizeCustomerId(optional($request->route('salesReturn'))->customer_id),
            'delivery-notes.store' => $this->normalizeCustomerId($request->input('customer_id')),
            'delivery-notes.admin-update', 'delivery-notes.cancel' => $this->normalizeCustomerId(optional($request->route('deliveryNote'))->customer_id),
            'order-notes.store' => $this->normalizeCustomerId($request->input('customer_id')),
            'order-notes.admin-update', 'order-notes.cancel' => $this->normalizeCustomerId(optional($request->route('orderNote'))->customer_id),
            'receivable-payments.admin-update', 'receivable-payments.cancel' => $this->normalizeCustomerId(optional($request->route('receivablePayment'))->customer_id),
            'receivables.customer-writeoff', 'receivables.customer-discount' => $this->normalizeCustomerId($request->route('customer')),
            default => $this->normalizeCustomerId($request->input('customer_id') ?? $request->route('customer')),
        };
    }

    private function normalizeCustomerId(mixed $value): ?int
    {
        if ($value instanceof Customer) {
            return $this->normalizeCustomerId($value->id);
        }
        if ($value instanceof SalesInvoice) {
            return $this->normalizeCustomerId($value->customer_id);
        }
        if ($value instanceof SalesReturn) {
            return $this->normalizeCustomerId($value->customer_id);
        }
        if ($value instanceof DeliveryNote) {
            return $this->normalizeCustomerId($value->customer_id);
        }
        if ($value instanceof OrderNote) {
            return $this->normalizeCustomerId($value->customer_id);
        }
        if ($value instanceof ReceivablePayment) {
            return $this->normalizeCustomerId($value->customer_id);
        }

        $customerId = (int) $value;

        return $customerId > 0 ? $customerId : null;
    }

    private function resolveSupplierId(Request $request): ?int
    {
        $routeName = (string) optional($request->route())->getName();

        return match ($routeName) {
            'outgoing-transactions.store', 'supplier-payables.store' => $this->normalizeSupplierId($request->input('supplier_id')),
            'outgoing-transactions.show', 'outgoing-transactions.print', 'outgoing-transactions.export.pdf', 'outgoing-transactions.export.excel' => $this->normalizeSupplierId(optional($request->route('outgoingTransaction'))->supplier_id),
            'supplier-payables.show-payment', 'supplier-payables.print-payment', 'supplier-payables.export-payment-pdf' => $this->normalizeSupplierId(optional($request->route('supplierPayment'))->supplier_id),
            default => $this->normalizeSupplierId($request->input('supplier_id') ?? $request->route('supplier')),
        };
    }

    private function normalizeSupplierId(mixed $value): ?int
    {
        if ($value instanceof Supplier) {
            return $this->normalizeSupplierId($value->id);
        }
        if ($value instanceof OutgoingTransaction) {
            return $this->normalizeSupplierId($value->supplier_id);
        }
        if ($value instanceof SupplierPayment) {
            return $this->normalizeSupplierId($value->supplier_id);
        }

        $supplierId = (int) $value;

        return $supplierId > 0 ? $supplierId : null;
    }
}
