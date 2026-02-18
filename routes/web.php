<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuditLogPageController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPageController;
use App\Http\Controllers\CustomerLevelController;
use App\Http\Controllers\CustomerLevelPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryNotePageController;
use App\Http\Controllers\ItemCategoryController;
use App\Http\Controllers\ItemCategoryPageController;
use App\Http\Controllers\OrderNotePageController;
use App\Http\Controllers\OutgoingTransactionPageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductPageController;
use App\Http\Controllers\ReceivablePageController;
use App\Http\Controllers\ReceivablePaymentPageController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\SalesInvoicePageController;
use App\Http\Controllers\SalesReturnPageController;
use App\Http\Controllers\SemesterTransactionPageController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupplierPageController;
use App\Http\Controllers\SupplierPayablePageController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::prefix('api')->name('api.')->group(function (): void {
    Route::apiResource('item-categories', ItemCategoryController::class);
    Route::apiResource('customer-levels', CustomerLevelController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('customers', CustomerController::class);
});

Route::middleware(['auth', 'prefs'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/sales-invoices', [SalesInvoicePageController::class, 'index'])->name('sales-invoices.index');
    Route::get('/sales-invoices/create', [SalesInvoicePageController::class, 'create'])->name('sales-invoices.create');
    Route::post('/sales-invoices', [SalesInvoicePageController::class, 'store'])->middleware(['finance.unlocked', 'semester.open', 'idempotent'])->name('sales-invoices.store');
    Route::get('/sales-invoices/{salesInvoice}', [SalesInvoicePageController::class, 'show'])->name('sales-invoices.show');
    Route::get('/sales-invoices/{salesInvoice}/print', [SalesInvoicePageController::class, 'print'])->name('sales-invoices.print');
    Route::get('/sales-invoices/{salesInvoice}/pdf', [SalesInvoicePageController::class, 'exportPdf'])->name('sales-invoices.export.pdf');
    Route::get('/sales-invoices/{salesInvoice}/excel', [SalesInvoicePageController::class, 'exportExcel'])->name('sales-invoices.export.excel');
    Route::put('/sales-invoices/{salesInvoice}/admin-update', [SalesInvoicePageController::class, 'adminUpdate'])
        ->middleware(['admin', 'semester.open'])
        ->name('sales-invoices.admin-update');
    Route::post('/sales-invoices/{salesInvoice}/cancel', [SalesInvoicePageController::class, 'cancel'])
        ->middleware(['admin', 'semester.open'])
        ->name('sales-invoices.cancel');

    Route::get('/sales-returns', [SalesReturnPageController::class, 'index'])->name('sales-returns.index');
    Route::get('/sales-returns/create', [SalesReturnPageController::class, 'create'])->name('sales-returns.create');
    Route::post('/sales-returns', [SalesReturnPageController::class, 'store'])->middleware(['finance.unlocked', 'semester.open', 'idempotent'])->name('sales-returns.store');
    Route::get('/sales-returns/{salesReturn}', [SalesReturnPageController::class, 'show'])->name('sales-returns.show');
    Route::get('/sales-returns/{salesReturn}/print', [SalesReturnPageController::class, 'print'])->name('sales-returns.print');
    Route::get('/sales-returns/{salesReturn}/pdf', [SalesReturnPageController::class, 'exportPdf'])->name('sales-returns.export.pdf');
    Route::get('/sales-returns/{salesReturn}/excel', [SalesReturnPageController::class, 'exportExcel'])->name('sales-returns.export.excel');
    Route::put('/sales-returns/{salesReturn}/admin-update', [SalesReturnPageController::class, 'adminUpdate'])
        ->middleware(['admin', 'semester.open'])
        ->name('sales-returns.admin-update');
    Route::post('/sales-returns/{salesReturn}/cancel', [SalesReturnPageController::class, 'cancel'])
        ->middleware(['admin', 'semester.open'])
        ->name('sales-returns.cancel');

    Route::get('/delivery-notes', [DeliveryNotePageController::class, 'index'])->name('delivery-notes.index');
    Route::get('/delivery-notes/create', [DeliveryNotePageController::class, 'create'])->name('delivery-notes.create');
    Route::post('/delivery-notes', [DeliveryNotePageController::class, 'store'])->middleware(['semester.open', 'idempotent'])->name('delivery-notes.store');
    Route::get('/delivery-notes/{deliveryNote}', [DeliveryNotePageController::class, 'show'])->name('delivery-notes.show');
    Route::get('/delivery-notes/{deliveryNote}/print', [DeliveryNotePageController::class, 'print'])->name('delivery-notes.print');
    Route::get('/delivery-notes/{deliveryNote}/pdf', [DeliveryNotePageController::class, 'exportPdf'])->name('delivery-notes.export.pdf');
    Route::get('/delivery-notes/{deliveryNote}/excel', [DeliveryNotePageController::class, 'exportExcel'])->name('delivery-notes.export.excel');
    Route::put('/delivery-notes/{deliveryNote}/admin-update', [DeliveryNotePageController::class, 'adminUpdate'])
        ->middleware(['admin', 'semester.open'])
        ->name('delivery-notes.admin-update');
    Route::post('/delivery-notes/{deliveryNote}/cancel', [DeliveryNotePageController::class, 'cancel'])
        ->middleware(['admin', 'semester.open'])
        ->name('delivery-notes.cancel');

    Route::get('/order-notes', [OrderNotePageController::class, 'index'])->name('order-notes.index');
    Route::get('/order-notes/create', [OrderNotePageController::class, 'create'])->name('order-notes.create');
    Route::post('/order-notes', [OrderNotePageController::class, 'store'])->middleware(['semester.open', 'idempotent'])->name('order-notes.store');
    Route::get('/order-notes/{orderNote}', [OrderNotePageController::class, 'show'])->name('order-notes.show');
    Route::get('/order-notes/{orderNote}/print', [OrderNotePageController::class, 'print'])->name('order-notes.print');
    Route::get('/order-notes/{orderNote}/pdf', [OrderNotePageController::class, 'exportPdf'])->name('order-notes.export.pdf');
    Route::get('/order-notes/{orderNote}/excel', [OrderNotePageController::class, 'exportExcel'])->name('order-notes.export.excel');
    Route::put('/order-notes/{orderNote}/admin-update', [OrderNotePageController::class, 'adminUpdate'])
        ->middleware(['admin', 'semester.open'])
        ->name('order-notes.admin-update');
    Route::post('/order-notes/{orderNote}/cancel', [OrderNotePageController::class, 'cancel'])
        ->middleware(['admin', 'semester.open'])
        ->name('order-notes.cancel');

    Route::get('/outgoing-transactions', [OutgoingTransactionPageController::class, 'index'])->name('outgoing-transactions.index');
    Route::get('/outgoing-transactions/create', [OutgoingTransactionPageController::class, 'create'])->name('outgoing-transactions.create');
    Route::post('/outgoing-transactions', [OutgoingTransactionPageController::class, 'store'])
        ->middleware(['finance.unlocked', 'semester.open', 'idempotent'])
        ->name('outgoing-transactions.store');
    Route::get('/outgoing-transactions/{outgoingTransaction}', [OutgoingTransactionPageController::class, 'show'])->name('outgoing-transactions.show');
    Route::get('/outgoing-transactions/{outgoingTransaction}/print', [OutgoingTransactionPageController::class, 'print'])->name('outgoing-transactions.print');
    Route::get('/outgoing-transactions/{outgoingTransaction}/pdf', [OutgoingTransactionPageController::class, 'exportPdf'])->name('outgoing-transactions.export.pdf');
    Route::get('/outgoing-transactions/{outgoingTransaction}/excel', [OutgoingTransactionPageController::class, 'exportExcel'])->name('outgoing-transactions.export.excel');
    Route::post('/outgoing-transactions/supplier/{supplier}/semester-close', [OutgoingTransactionPageController::class, 'closeSupplierSemester'])
        ->middleware('admin')
        ->name('outgoing-transactions.supplier-semester.close');
    Route::post('/outgoing-transactions/supplier/{supplier}/semester-open', [OutgoingTransactionPageController::class, 'openSupplierSemester'])
        ->middleware('admin')
        ->name('outgoing-transactions.supplier-semester.open');

    Route::get('/receivables', [ReceivablePageController::class, 'index'])->name('receivables.index');
    Route::get('/receivables/customer/{customer}/print-bill', [ReceivablePageController::class, 'printCustomerBill'])
        ->name('receivables.print-customer-bill');
    Route::get('/receivables/customer/{customer}/bill-pdf', [ReceivablePageController::class, 'exportCustomerBillPdf'])
        ->name('receivables.export-customer-bill-pdf');
    Route::get('/receivables/customer/{customer}/bill-excel', [ReceivablePageController::class, 'exportCustomerBillExcel'])
        ->name('receivables.export-customer-bill-excel');
    Route::post('/receivables/customer-writeoff/{customer}', [ReceivablePageController::class, 'customerWriteoff'])
        ->middleware(['finance.unlocked', 'admin', 'semester.open'])
        ->name('receivables.customer-writeoff');
    Route::post('/receivables/customer-discount/{customer}', [ReceivablePageController::class, 'customerDiscount'])
        ->middleware(['finance.unlocked', 'admin', 'semester.open'])
        ->name('receivables.customer-discount');
    Route::post('/receivables/customer/{customer}/semester-close', [ReceivablePageController::class, 'closeCustomerSemester'])
        ->middleware('admin')
        ->name('receivables.customer-semester.close');
    Route::post('/receivables/customer/{customer}/semester-open', [ReceivablePageController::class, 'openCustomerSemester'])
        ->middleware('admin')
        ->name('receivables.customer-semester.open');
    Route::get('/receivable-payments', [ReceivablePaymentPageController::class, 'index'])->name('receivable-payments.index');
    Route::get('/receivable-payments/create', [ReceivablePaymentPageController::class, 'create'])->name('receivable-payments.create');
    Route::post('/receivable-payments', [ReceivablePaymentPageController::class, 'store'])
        ->middleware(['finance.unlocked', 'semester.open', 'idempotent'])
        ->name('receivable-payments.store');
    Route::get('/receivable-payments/{receivablePayment}', [ReceivablePaymentPageController::class, 'show'])->name('receivable-payments.show');
    Route::get('/receivable-payments/{receivablePayment}/print', [ReceivablePaymentPageController::class, 'print'])->name('receivable-payments.print');
    Route::get('/receivable-payments/{receivablePayment}/pdf', [ReceivablePaymentPageController::class, 'exportPdf'])->name('receivable-payments.export.pdf');
    Route::put('/receivable-payments/{receivablePayment}/admin-update', [ReceivablePaymentPageController::class, 'adminUpdate'])
        ->middleware(['admin', 'semester.open'])
        ->name('receivable-payments.admin-update');
    Route::post('/receivable-payments/{receivablePayment}/cancel', [ReceivablePaymentPageController::class, 'cancel'])
        ->middleware(['admin', 'semester.open'])
        ->name('receivable-payments.cancel');

    Route::get('/reports', [ReportExportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{dataset}/csv', [ReportExportController::class, 'exportCsv'])->name('reports.export.csv');
    Route::get('/reports/{dataset}/pdf', [ReportExportController::class, 'exportPdf'])->name('reports.export.pdf');
    Route::get('/reports/{dataset}/print', [ReportExportController::class, 'print'])->name('reports.print');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/semester-close', [SettingsController::class, 'closeSemester'])
        ->middleware('admin')
        ->name('settings.semester.close');
    Route::post('/settings/semester-open', [SettingsController::class, 'openSemester'])
        ->middleware('admin')
        ->name('settings.semester.open');

    Route::get('/suppliers', [SupplierPageController::class, 'index'])->name('suppliers.index');
    Route::get('/suppliers/lookup', [SupplierPageController::class, 'lookup'])->name('suppliers.lookup');
    Route::get('/suppliers/{supplier}/edit', [SupplierPageController::class, 'edit'])->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [SupplierPageController::class, 'update'])->name('suppliers.update');
    Route::get('/supplier-payables', [SupplierPayablePageController::class, 'index'])->name('supplier-payables.index');
    Route::get('/supplier-payables/create', [SupplierPayablePageController::class, 'create'])->name('supplier-payables.create');
    Route::post('/supplier-payables', [SupplierPayablePageController::class, 'store'])
        ->middleware(['finance.unlocked', 'semester.open', 'idempotent'])
        ->name('supplier-payables.store');
    Route::get('/supplier-payables/payment/{supplierPayment}', [SupplierPayablePageController::class, 'showPayment'])->name('supplier-payables.show-payment');
    Route::get('/supplier-payables/payment/{supplierPayment}/print', [SupplierPayablePageController::class, 'printPayment'])->name('supplier-payables.print-payment');
    Route::get('/supplier-payables/payment/{supplierPayment}/pdf', [SupplierPayablePageController::class, 'exportPaymentPdf'])->name('supplier-payables.export-payment-pdf');

    Route::middleware('admin')->group(function (): void {
        Route::get('/item-categories', [ItemCategoryPageController::class, 'index'])->name('item-categories.index');
        Route::get('/item-categories/create', [ItemCategoryPageController::class, 'create'])->name('item-categories.create');
        Route::post('/item-categories', [ItemCategoryPageController::class, 'store'])->name('item-categories.store');
        Route::get('/item-categories/{itemCategory}/edit', [ItemCategoryPageController::class, 'edit'])->name('item-categories.edit');
        Route::put('/item-categories/{itemCategory}', [ItemCategoryPageController::class, 'update'])->name('item-categories.update');
        Route::delete('/item-categories/{itemCategory}', [ItemCategoryPageController::class, 'destroy'])->name('item-categories.destroy');

        Route::get('/products', [ProductPageController::class, 'index'])->name('products.index');
        Route::get('/products/export.csv', [ProductPageController::class, 'exportCsv'])->name('products.export.csv');
        Route::get('/products/create', [ProductPageController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductPageController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [ProductPageController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [ProductPageController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductPageController::class, 'destroy'])->name('products.destroy');

        Route::get('/customer-levels-web', [CustomerLevelPageController::class, 'index'])->name('customer-levels-web.index');
        Route::get('/customer-levels-web/create', [CustomerLevelPageController::class, 'create'])->name('customer-levels-web.create');
        Route::post('/customer-levels-web', [CustomerLevelPageController::class, 'store'])->name('customer-levels-web.store');
        Route::get('/customer-levels-web/{customerLevel}/edit', [CustomerLevelPageController::class, 'edit'])->name('customer-levels-web.edit');
        Route::put('/customer-levels-web/{customerLevel}', [CustomerLevelPageController::class, 'update'])->name('customer-levels-web.update');
        Route::delete('/customer-levels-web/{customerLevel}', [CustomerLevelPageController::class, 'destroy'])->name('customer-levels-web.destroy');

        Route::get('/customers-web', [CustomerPageController::class, 'index'])->name('customers-web.index');
        Route::get('/customers-web/export.csv', [CustomerPageController::class, 'exportCsv'])->name('customers-web.export.csv');
        Route::get('/customers-web/create', [CustomerPageController::class, 'create'])->name('customers-web.create');
        Route::post('/customers-web', [CustomerPageController::class, 'store'])->name('customers-web.store');
        Route::get('/customers-web/{customer}/edit', [CustomerPageController::class, 'edit'])->name('customers-web.edit');
        Route::put('/customers-web/{customer}', [CustomerPageController::class, 'update'])->name('customers-web.update');
        Route::delete('/customers-web/{customer}', [CustomerPageController::class, 'destroy'])->name('customers-web.destroy');

        Route::get('/suppliers/create', [SupplierPageController::class, 'create'])->name('suppliers.create');
        Route::post('/suppliers', [SupplierPageController::class, 'store'])->name('suppliers.store');
        Route::delete('/suppliers/{supplier}', [SupplierPageController::class, 'destroy'])->name('suppliers.destroy');

        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
        Route::get('/audit-logs', [AuditLogPageController::class, 'index'])->name('audit-logs.index');
        Route::get('/audit-logs/export.csv', [AuditLogPageController::class, 'exportCsv'])->name('audit-logs.export.csv');
        Route::get('/semester-transactions', [SemesterTransactionPageController::class, 'index'])->name('semester-transactions.index');
    });
});
