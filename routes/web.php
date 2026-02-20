<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuditLogPageController;
use App\Http\Controllers\ApprovalRequestController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPageController;
use App\Http\Controllers\CustomerShipLocationPageController;
use App\Http\Controllers\CustomerLevelController;
use App\Http\Controllers\CustomerLevelPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryNotePageController;
use App\Http\Controllers\ItemCategoryController;
use App\Http\Controllers\ItemCategoryPageController;
use App\Http\Controllers\MassImportController;
use App\Http\Controllers\OrderNotePageController;
use App\Http\Controllers\OpsHealthController;
use App\Http\Controllers\OutgoingTransactionPageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductPageController;
use App\Http\Controllers\ReceivablePageController;
use App\Http\Controllers\ReceivablePaymentPageController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\SalesInvoicePageController;
use App\Http\Controllers\SalesReturnPageController;
use App\Http\Controllers\SchoolBulkTransactionPageController;
use App\Http\Controllers\SemesterTransactionPageController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupplierPageController;
use App\Http\Controllers\SupplierPayablePageController;
use App\Http\Controllers\TransactionCorrectionWizardController;
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
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('perm:dashboard.view')->name('dashboard');

    Route::get('/sales-invoices', [SalesInvoicePageController::class, 'index'])->middleware('perm:transactions.view')->name('sales-invoices.index');
    Route::get('/sales-invoices/create', [SalesInvoicePageController::class, 'create'])->middleware('perm:transactions.create')->name('sales-invoices.create');
    Route::post('/sales-invoices', [SalesInvoicePageController::class, 'store'])->middleware(['finance.unlocked', 'semester.open', 'idempotent'])->name('sales-invoices.store');
    Route::get('/sales-invoices/{salesInvoice}', [SalesInvoicePageController::class, 'show'])->middleware('perm:transactions.view')->name('sales-invoices.show');
    Route::get('/sales-invoices/{salesInvoice}/print', [SalesInvoicePageController::class, 'print'])->middleware('perm:transactions.export')->name('sales-invoices.print');
    Route::get('/sales-invoices/{salesInvoice}/pdf', [SalesInvoicePageController::class, 'exportPdf'])->middleware('perm:transactions.export')->name('sales-invoices.export.pdf');
    Route::get('/sales-invoices/{salesInvoice}/excel', [SalesInvoicePageController::class, 'exportExcel'])->middleware('perm:transactions.export')->name('sales-invoices.export.excel');
    Route::put('/sales-invoices/{salesInvoice}/admin-update', [SalesInvoicePageController::class, 'adminUpdate'])
        ->middleware(['admin', 'perm:transactions.correction.approve', 'semester.open'])
        ->name('sales-invoices.admin-update');
    Route::post('/sales-invoices/{salesInvoice}/cancel', [SalesInvoicePageController::class, 'cancel'])
        ->middleware(['admin', 'perm:transactions.cancel', 'semester.open'])
        ->name('sales-invoices.cancel');

    Route::get('/sales-returns', [SalesReturnPageController::class, 'index'])->middleware('perm:transactions.view')->name('sales-returns.index');
    Route::get('/sales-returns/create', [SalesReturnPageController::class, 'create'])->middleware('perm:transactions.create')->name('sales-returns.create');
    Route::post('/sales-returns', [SalesReturnPageController::class, 'store'])->middleware(['finance.unlocked', 'semester.open', 'idempotent'])->name('sales-returns.store');
    Route::get('/sales-returns/{salesReturn}', [SalesReturnPageController::class, 'show'])->middleware('perm:transactions.view')->name('sales-returns.show');
    Route::get('/sales-returns/{salesReturn}/print', [SalesReturnPageController::class, 'print'])->middleware('perm:transactions.export')->name('sales-returns.print');
    Route::get('/sales-returns/{salesReturn}/pdf', [SalesReturnPageController::class, 'exportPdf'])->middleware('perm:transactions.export')->name('sales-returns.export.pdf');
    Route::get('/sales-returns/{salesReturn}/excel', [SalesReturnPageController::class, 'exportExcel'])->middleware('perm:transactions.export')->name('sales-returns.export.excel');
    Route::put('/sales-returns/{salesReturn}/admin-update', [SalesReturnPageController::class, 'adminUpdate'])
        ->middleware(['admin', 'perm:transactions.correction.approve', 'semester.open'])
        ->name('sales-returns.admin-update');
    Route::post('/sales-returns/{salesReturn}/cancel', [SalesReturnPageController::class, 'cancel'])
        ->middleware(['admin', 'perm:transactions.cancel', 'semester.open'])
        ->name('sales-returns.cancel');

    Route::get('/delivery-notes', [DeliveryNotePageController::class, 'index'])->middleware('perm:transactions.view')->name('delivery-notes.index');
    Route::get('/delivery-notes/create', [DeliveryNotePageController::class, 'create'])->middleware('perm:transactions.create')->name('delivery-notes.create');
    Route::post('/delivery-notes', [DeliveryNotePageController::class, 'store'])->middleware(['semester.open', 'idempotent'])->name('delivery-notes.store');
    Route::get('/delivery-notes/{deliveryNote}', [DeliveryNotePageController::class, 'show'])->middleware('perm:transactions.view')->name('delivery-notes.show');
    Route::get('/delivery-notes/{deliveryNote}/print', [DeliveryNotePageController::class, 'print'])->middleware('perm:transactions.export')->name('delivery-notes.print');
    Route::get('/delivery-notes/{deliveryNote}/pdf', [DeliveryNotePageController::class, 'exportPdf'])->middleware('perm:transactions.export')->name('delivery-notes.export.pdf');
    Route::get('/delivery-notes/{deliveryNote}/excel', [DeliveryNotePageController::class, 'exportExcel'])->middleware('perm:transactions.export')->name('delivery-notes.export.excel');
    Route::put('/delivery-notes/{deliveryNote}/admin-update', [DeliveryNotePageController::class, 'adminUpdate'])
        ->middleware(['admin', 'perm:transactions.correction.approve', 'semester.open'])
        ->name('delivery-notes.admin-update');
    Route::post('/delivery-notes/{deliveryNote}/cancel', [DeliveryNotePageController::class, 'cancel'])
        ->middleware(['admin', 'perm:transactions.cancel', 'semester.open'])
        ->name('delivery-notes.cancel');

    Route::get('/order-notes', [OrderNotePageController::class, 'index'])->middleware('perm:transactions.view')->name('order-notes.index');
    Route::get('/order-notes/create', [OrderNotePageController::class, 'create'])->middleware('perm:transactions.create')->name('order-notes.create');
    Route::post('/order-notes', [OrderNotePageController::class, 'store'])->middleware(['semester.open', 'idempotent'])->name('order-notes.store');
    Route::get('/order-notes/{orderNote}', [OrderNotePageController::class, 'show'])->middleware('perm:transactions.view')->name('order-notes.show');
    Route::get('/order-notes/{orderNote}/print', [OrderNotePageController::class, 'print'])->middleware('perm:transactions.export')->name('order-notes.print');
    Route::get('/order-notes/{orderNote}/pdf', [OrderNotePageController::class, 'exportPdf'])->middleware('perm:transactions.export')->name('order-notes.export.pdf');
    Route::get('/order-notes/{orderNote}/excel', [OrderNotePageController::class, 'exportExcel'])->middleware('perm:transactions.export')->name('order-notes.export.excel');
    Route::put('/order-notes/{orderNote}/admin-update', [OrderNotePageController::class, 'adminUpdate'])
        ->middleware(['admin', 'perm:transactions.correction.approve', 'semester.open'])
        ->name('order-notes.admin-update');
    Route::post('/order-notes/{orderNote}/cancel', [OrderNotePageController::class, 'cancel'])
        ->middleware(['admin', 'perm:transactions.cancel', 'semester.open'])
        ->name('order-notes.cancel');

    Route::get('/outgoing-transactions', [OutgoingTransactionPageController::class, 'index'])->middleware('perm:transactions.view')->name('outgoing-transactions.index');
    Route::get('/outgoing-transactions/create', [OutgoingTransactionPageController::class, 'create'])->middleware('perm:transactions.create')->name('outgoing-transactions.create');
    Route::post('/outgoing-transactions', [OutgoingTransactionPageController::class, 'store'])
        ->middleware(['finance.unlocked', 'semester.open', 'idempotent'])
        ->name('outgoing-transactions.store');
    Route::get('/outgoing-transactions/{outgoingTransaction}', [OutgoingTransactionPageController::class, 'show'])->middleware('perm:transactions.view')->name('outgoing-transactions.show');
    Route::get('/outgoing-transactions/{outgoingTransaction}/print', [OutgoingTransactionPageController::class, 'print'])->middleware('perm:transactions.export')->name('outgoing-transactions.print');
    Route::get('/outgoing-transactions/{outgoingTransaction}/pdf', [OutgoingTransactionPageController::class, 'exportPdf'])->middleware('perm:transactions.export')->name('outgoing-transactions.export.pdf');
    Route::get('/outgoing-transactions/{outgoingTransaction}/excel', [OutgoingTransactionPageController::class, 'exportExcel'])->middleware('perm:transactions.export')->name('outgoing-transactions.export.excel');
    Route::put('/outgoing-transactions/{outgoingTransaction}/admin-update', [OutgoingTransactionPageController::class, 'adminUpdate'])
        ->middleware(['admin', 'perm:transactions.correction.approve', 'semester.open'])
        ->name('outgoing-transactions.admin-update');
    Route::post('/outgoing-transactions/supplier/{supplier}/semester-close', [OutgoingTransactionPageController::class, 'closeSupplierSemester'])
        ->middleware('admin')
        ->name('outgoing-transactions.supplier-semester.close');
    Route::post('/outgoing-transactions/supplier/{supplier}/semester-open', [OutgoingTransactionPageController::class, 'openSupplierSemester'])
        ->middleware('admin')
        ->name('outgoing-transactions.supplier-semester.open');

    Route::get('/receivables', [ReceivablePageController::class, 'index'])->middleware('perm:receivables.view')->name('receivables.index');
    Route::get('/receivables/customer/{customer}/print-bill', [ReceivablePageController::class, 'printCustomerBill'])
        ->middleware('perm:receivables.view')
        ->name('receivables.print-customer-bill');
    Route::get('/receivables/customer/{customer}/bill-pdf', [ReceivablePageController::class, 'exportCustomerBillPdf'])
        ->middleware('perm:receivables.view')
        ->name('receivables.export-customer-bill-pdf');
    Route::get('/receivables/customer/{customer}/bill-excel', [ReceivablePageController::class, 'exportCustomerBillExcel'])
        ->middleware('perm:receivables.view')
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
    Route::get('/receivable-payments', [ReceivablePaymentPageController::class, 'index'])->middleware('perm:receivables.view')->name('receivable-payments.index');
    Route::get('/receivable-payments/create', [ReceivablePaymentPageController::class, 'create'])->middleware('perm:receivables.pay')->name('receivable-payments.create');
    Route::post('/receivable-payments', [ReceivablePaymentPageController::class, 'store'])
        ->middleware(['finance.unlocked', 'semester.open', 'idempotent'])
        ->name('receivable-payments.store');
    Route::get('/receivable-payments/{receivablePayment}', [ReceivablePaymentPageController::class, 'show'])->middleware('perm:receivables.view')->name('receivable-payments.show');
    Route::get('/receivable-payments/{receivablePayment}/print', [ReceivablePaymentPageController::class, 'print'])->middleware('perm:receivables.view')->name('receivable-payments.print');
    Route::get('/receivable-payments/{receivablePayment}/pdf', [ReceivablePaymentPageController::class, 'exportPdf'])->middleware('perm:receivables.view')->name('receivable-payments.export.pdf');
    Route::put('/receivable-payments/{receivablePayment}/admin-update', [ReceivablePaymentPageController::class, 'adminUpdate'])
        ->middleware(['admin', 'perm:transactions.correction.approve', 'semester.open'])
        ->name('receivable-payments.admin-update');
    Route::post('/receivable-payments/{receivablePayment}/cancel', [ReceivablePaymentPageController::class, 'cancel'])
        ->middleware(['admin', 'perm:transactions.cancel', 'semester.open'])
        ->name('receivable-payments.cancel');
    Route::get('/transaction-corrections/create', [TransactionCorrectionWizardController::class, 'create'])
        ->middleware('perm:transactions.correction.request')
        ->name('transaction-corrections.create');
    Route::post('/transaction-corrections', [TransactionCorrectionWizardController::class, 'store'])
        ->middleware('perm:transactions.correction.request')
        ->name('transaction-corrections.store');
    Route::post('/transaction-corrections/preview-stock', [TransactionCorrectionWizardController::class, 'stockImpactPreview'])
        ->middleware('perm:transactions.correction.request')
        ->name('transaction-corrections.preview-stock');

    Route::get('/customer-ship-locations', [CustomerShipLocationPageController::class, 'index'])->middleware('perm:transactions.view')->name('customer-ship-locations.index');
    Route::get('/customer-ship-locations/create', [CustomerShipLocationPageController::class, 'create'])->middleware('perm:transactions.create')->name('customer-ship-locations.create');
    Route::post('/customer-ship-locations', [CustomerShipLocationPageController::class, 'store'])->middleware('perm:transactions.create')->name('customer-ship-locations.store');
    Route::get('/customer-ship-locations/{customerShipLocation}/edit', [CustomerShipLocationPageController::class, 'edit'])->middleware('perm:transactions.create')->name('customer-ship-locations.edit');
    Route::put('/customer-ship-locations/{customerShipLocation}', [CustomerShipLocationPageController::class, 'update'])->middleware('perm:transactions.create')->name('customer-ship-locations.update');
    Route::delete('/customer-ship-locations/{customerShipLocation}', [CustomerShipLocationPageController::class, 'destroy'])->middleware('perm:transactions.create')->name('customer-ship-locations.destroy');
    Route::get('/customer-ship-locations/lookup', [CustomerShipLocationPageController::class, 'lookup'])->middleware('perm:transactions.create')->name('customer-ship-locations.lookup');

    Route::get('/school-bulk-transactions', [SchoolBulkTransactionPageController::class, 'index'])->middleware('perm:transactions.view')->name('school-bulk-transactions.index');
    Route::get('/school-bulk-transactions/create', [SchoolBulkTransactionPageController::class, 'create'])->middleware('perm:transactions.create')->name('school-bulk-transactions.create');
    Route::post('/school-bulk-transactions', [SchoolBulkTransactionPageController::class, 'store'])->middleware(['semester.open', 'idempotent'])->name('school-bulk-transactions.store');
    Route::post('/school-bulk-transactions/{schoolBulkTransaction}/generate-invoices', [SchoolBulkTransactionPageController::class, 'generateInvoices'])
        ->middleware(['perm:transactions.create', 'finance.unlocked', 'semester.open', 'idempotent'])
        ->name('school-bulk-transactions.generate-invoices');
    Route::get('/school-bulk-transactions/{schoolBulkTransaction}', [SchoolBulkTransactionPageController::class, 'show'])->middleware('perm:transactions.view')->name('school-bulk-transactions.show');
    Route::get('/school-bulk-transactions/{schoolBulkTransaction}/print', [SchoolBulkTransactionPageController::class, 'print'])->middleware('perm:transactions.export')->name('school-bulk-transactions.print');
    Route::get('/school-bulk-transactions/{schoolBulkTransaction}/pdf', [SchoolBulkTransactionPageController::class, 'exportPdf'])->middleware('perm:transactions.export')->name('school-bulk-transactions.export.pdf');
    Route::get('/school-bulk-transactions/{schoolBulkTransaction}/excel', [SchoolBulkTransactionPageController::class, 'exportExcel'])->middleware('perm:transactions.export')->name('school-bulk-transactions.export.excel');

    Route::get('/reports', [ReportExportController::class, 'index'])->middleware('perm:reports.view')->name('reports.index');
    Route::get('/reports/{dataset}/csv', [ReportExportController::class, 'exportCsv'])->middleware('perm:reports.export')->name('reports.export.csv');
    Route::get('/reports/{dataset}/pdf', [ReportExportController::class, 'exportPdf'])->middleware('perm:reports.export')->name('reports.export.pdf');
    Route::get('/reports/{dataset}/print', [ReportExportController::class, 'print'])->middleware('perm:reports.export')->name('reports.print');
    Route::get('/reports/{dataset}/queue/{format}', [ReportExportController::class, 'queueExport'])->middleware('perm:reports.export')->name('reports.queue');
    Route::get('/reports/queued/{task}/download', [ReportExportController::class, 'downloadQueuedExport'])->middleware('perm:reports.export')->name('reports.queue.download');
    Route::get('/reports/queued/status', [ReportExportController::class, 'queuedExportsStatus'])->middleware('perm:reports.export')->name('reports.queue.status');
    Route::post('/reports/queued/{task}/retry', [ReportExportController::class, 'retryQueuedExport'])->middleware('perm:reports.export')->name('reports.queue.retry');
    Route::post('/reports/queued/{task}/cancel', [ReportExportController::class, 'cancelQueuedExport'])->middleware('perm:reports.export')->name('reports.queue.cancel');
    Route::get('/settings', [SettingsController::class, 'edit'])->middleware('perm:settings.profile')->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->middleware('perm:settings.profile')->name('settings.update');
    Route::post('/settings/semester-close', [SettingsController::class, 'closeSemester'])
        ->middleware(['admin', 'perm:settings.admin'])
        ->name('settings.semester.close');
    Route::post('/settings/semester-open', [SettingsController::class, 'openSemester'])
        ->middleware(['admin', 'perm:settings.admin'])
        ->name('settings.semester.open');

    Route::get('/suppliers', [SupplierPageController::class, 'index'])->middleware('perm:masters.suppliers.view')->name('suppliers.index');
    Route::get('/suppliers/lookup', [SupplierPageController::class, 'lookup'])->name('suppliers.lookup');
    Route::get('/suppliers/{supplier}/edit', [SupplierPageController::class, 'edit'])->middleware('perm:masters.suppliers.edit')->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [SupplierPageController::class, 'update'])->middleware('perm:masters.suppliers.edit')->name('suppliers.update');
    Route::get('/supplier-payables', [SupplierPayablePageController::class, 'index'])->middleware('perm:supplier_payables.view')->name('supplier-payables.index');
    Route::get('/supplier-payables/create', [SupplierPayablePageController::class, 'create'])->middleware('perm:supplier_payables.pay')->name('supplier-payables.create');
    Route::post('/supplier-payables', [SupplierPayablePageController::class, 'store'])
        ->middleware(['finance.unlocked', 'semester.open', 'idempotent'])
        ->name('supplier-payables.store');
    Route::get('/supplier-payables/payment/{supplierPayment}', [SupplierPayablePageController::class, 'showPayment'])->middleware('perm:supplier_payables.view')->name('supplier-payables.show-payment');
    Route::get('/supplier-payables/payment/{supplierPayment}/print', [SupplierPayablePageController::class, 'printPayment'])->middleware('perm:supplier_payables.view')->name('supplier-payables.print-payment');
    Route::get('/supplier-payables/payment/{supplierPayment}/pdf', [SupplierPayablePageController::class, 'exportPaymentPdf'])->middleware('perm:supplier_payables.view')->name('supplier-payables.export-payment-pdf');
    Route::put('/supplier-payables/payment/{supplierPayment}/admin-update', [SupplierPayablePageController::class, 'adminUpdate'])
        ->middleware(['admin', 'perm:transactions.correction.approve', 'semester.open'])
        ->name('supplier-payables.admin-update');

    Route::middleware('admin')->group(function (): void {
        Route::get('/item-categories', [ItemCategoryPageController::class, 'index'])->middleware('perm:masters.products.manage')->name('item-categories.index');
        Route::get('/item-categories/create', [ItemCategoryPageController::class, 'create'])->name('item-categories.create');
        Route::post('/item-categories', [ItemCategoryPageController::class, 'store'])->name('item-categories.store');
        Route::get('/item-categories/{itemCategory}/edit', [ItemCategoryPageController::class, 'edit'])->name('item-categories.edit');
        Route::put('/item-categories/{itemCategory}', [ItemCategoryPageController::class, 'update'])->name('item-categories.update');
        Route::delete('/item-categories/{itemCategory}', [ItemCategoryPageController::class, 'destroy'])->name('item-categories.destroy');

        Route::get('/products', [ProductPageController::class, 'index'])->middleware('perm:masters.products.view')->name('products.index');
        Route::get('/products/export.csv', [ProductPageController::class, 'exportCsv'])->middleware('perm:masters.products.view')->name('products.export.csv');
        Route::get('/products/import/template', [MassImportController::class, 'templateProducts'])->middleware('perm:masters.products.manage')->name('products.import.template');
        Route::post('/products/import', [MassImportController::class, 'importProducts'])->middleware('perm:masters.products.manage')->name('products.import');
        Route::get('/sales-invoices/import/template', [MassImportController::class, 'templateSalesInvoices'])->middleware('perm:imports.transactions')->name('sales-invoices.import.template');
        Route::post('/sales-invoices/import', [MassImportController::class, 'importSalesInvoices'])->middleware('perm:imports.transactions')->name('sales-invoices.import');
        Route::get('/products/create', [ProductPageController::class, 'create'])->middleware('perm:masters.products.manage')->name('products.create');
        Route::post('/products', [ProductPageController::class, 'store'])->middleware('perm:masters.products.manage')->name('products.store');
        Route::get('/products/{product}/mutations', [ProductPageController::class, 'mutations'])->middleware('perm:masters.products.view')->name('products.mutations');
        Route::get('/products/{product}/edit', [ProductPageController::class, 'edit'])->middleware('perm:masters.products.manage')->name('products.edit');
        Route::put('/products/{product}', [ProductPageController::class, 'update'])->middleware('perm:masters.products.manage')->name('products.update');
        Route::delete('/products/{product}', [ProductPageController::class, 'destroy'])->middleware('perm:masters.products.manage')->name('products.destroy');

        Route::get('/customer-levels-web', [CustomerLevelPageController::class, 'index'])->name('customer-levels-web.index');
        Route::get('/customer-levels-web/create', [CustomerLevelPageController::class, 'create'])->name('customer-levels-web.create');
        Route::post('/customer-levels-web', [CustomerLevelPageController::class, 'store'])->name('customer-levels-web.store');
        Route::get('/customer-levels-web/{customerLevel}/edit', [CustomerLevelPageController::class, 'edit'])->name('customer-levels-web.edit');
        Route::put('/customer-levels-web/{customerLevel}', [CustomerLevelPageController::class, 'update'])->name('customer-levels-web.update');
        Route::delete('/customer-levels-web/{customerLevel}', [CustomerLevelPageController::class, 'destroy'])->name('customer-levels-web.destroy');

        Route::get('/customers-web', [CustomerPageController::class, 'index'])->middleware('perm:masters.customers.view')->name('customers-web.index');
        Route::get('/customers-web/export.csv', [CustomerPageController::class, 'exportCsv'])->middleware('perm:masters.customers.view')->name('customers-web.export.csv');
        Route::get('/customers-web/import/template', [MassImportController::class, 'templateCustomers'])->middleware('perm:masters.customers.manage')->name('customers-web.import.template');
        Route::post('/customers-web/import', [MassImportController::class, 'importCustomers'])->middleware('perm:masters.customers.manage')->name('customers-web.import');
        Route::get('/customers-web/create', [CustomerPageController::class, 'create'])->middleware('perm:masters.customers.manage')->name('customers-web.create');
        Route::post('/customers-web', [CustomerPageController::class, 'store'])->middleware('perm:masters.customers.manage')->name('customers-web.store');
        Route::get('/customers-web/{customer}/edit', [CustomerPageController::class, 'edit'])->middleware('perm:masters.customers.manage')->name('customers-web.edit');
        Route::put('/customers-web/{customer}', [CustomerPageController::class, 'update'])->middleware('perm:masters.customers.manage')->name('customers-web.update');
        Route::delete('/customers-web/{customer}', [CustomerPageController::class, 'destroy'])->middleware('perm:masters.customers.manage')->name('customers-web.destroy');

        Route::get('/suppliers/create', [SupplierPageController::class, 'create'])->name('suppliers.create');
        Route::get('/suppliers/import/template', [MassImportController::class, 'templateSuppliers'])->middleware('perm:masters.suppliers.edit')->name('suppliers.import.template');
        Route::post('/suppliers/import', [MassImportController::class, 'importSuppliers'])->middleware('perm:masters.suppliers.edit')->name('suppliers.import');
        Route::post('/suppliers', [SupplierPageController::class, 'store'])->name('suppliers.store');
        Route::delete('/suppliers/{supplier}', [SupplierPageController::class, 'destroy'])->name('suppliers.destroy');

        Route::get('/users', [UserManagementController::class, 'index'])->middleware('perm:users.manage')->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->middleware('perm:users.manage')->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->middleware('perm:users.manage')->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->middleware('perm:users.manage')->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->middleware('perm:users.manage')->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->middleware('perm:users.manage')->name('users.destroy');
        Route::get('/audit-logs', [AuditLogPageController::class, 'index'])->middleware('perm:audit_logs.view')->name('audit-logs.index');
        Route::get('/audit-logs/export.csv', [AuditLogPageController::class, 'exportCsv'])->middleware('perm:audit_logs.view')->name('audit-logs.export.csv');
        Route::get('/semester-transactions', [SemesterTransactionPageController::class, 'index'])->middleware('perm:settings.admin')->name('semester-transactions.index');
        Route::post('/semester-transactions/bulk-action', [SemesterTransactionPageController::class, 'bulkAction'])->middleware('perm:semester.bulk')->name('semester-transactions.bulk-action');
        Route::get('/approvals', [ApprovalRequestController::class, 'index'])->middleware('perm:transactions.correction.approve')->name('approvals.index');
        Route::post('/approvals/{approvalRequest}/approve', [ApprovalRequestController::class, 'approve'])->middleware(['perm:transactions.correction.approve', 'idempotent'])->name('approvals.approve');
        Route::post('/approvals/{approvalRequest}/reject', [ApprovalRequestController::class, 'reject'])->middleware(['perm:transactions.correction.approve', 'idempotent'])->name('approvals.reject');
        Route::post('/approvals/{approvalRequest}/re-execute', [ApprovalRequestController::class, 'reExecute'])->middleware(['perm:transactions.correction.approve', 'idempotent'])->name('approvals.re-execute');
        Route::get('/ops-health', [OpsHealthController::class, 'index'])->middleware('perm:settings.admin')->name('ops-health.index');
    });
});
